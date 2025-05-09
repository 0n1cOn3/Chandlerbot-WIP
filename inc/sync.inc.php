<?php

namespace App\Service;

use PDO;
use PDOException;
use Psr\Log\LoggerInterface;

class ForwardQueueHandler
{
    private PDO $pdo;
    private LoggerInterface $logger;
    private array $toForward = [];

    public function __construct(PDO $pdo, LoggerInterface $logger)
    {
        $this->pdo = $pdo;
        $this->logger = $logger;
    }

    public function buildToForwardKey($from, $to, $msgid, $topic): string
    {
        return $from . '_' . $to . '_' . $msgid . '_' . ($topic ?? '');
    }

    public function fillForwardArray($from, $to, $msgid, $payload, $topic = null): void
    {
        $key = $this->buildToForwardKey($from, $to, $msgid, $topic);
        $this->toForward[$key] = true;
        
        try {
            $stmt = $this->pdo->prepare("INSERT INTO forwardqueue (forwardkey, payload) VALUES (?, ?)");
            $stmt->execute([$key, json_encode($payload)]);
        } catch (PDOException $e) {
            $this->logger->warning("Failed to enqueue forward task: " . $e->getMessage());
        }
    }

    public function syncCheckTargetChannel($target, $meta): ?array
    {
        $query = "SELECT msgid FROM msgmeta WHERE
                    filedate = :filedate AND
                    filesize = :filesize AND
                    runtime = :runtime AND
                    width = :width AND
                    height = :height AND
                    chat = :chat
                    LIMIT 1";
        $this->pdo->exec("SET enable_seqscan = off");
        
        $stmt = $this->pdo->prepare($query);
        $stmt->execute([
            ':filedate' => $meta['filedate'],
            ':filesize' => $meta['filesize'],
            ':runtime' => $meta['runtime'],
            ':width' => $meta['width'],
            ':height' => $meta['height'],
            ':chat' => $target
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function syncMsgBefore($msgid, $channel): ?array
    {
        $query = "SELECT msgid, grouped_id FROM msgmeta WHERE msgid < :msgid AND chat = :chat AND type = 'photo' ORDER BY msgid DESC LIMIT 1";
        $stmt = $this->pdo->prepare($query);
        $stmt->execute([':msgid' => $msgid, ':chat' => $channel]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function syncGatherAlbumPosts($channel, $grouped_id): array
    {
        $stmt = $this->pdo->prepare("SELECT msgid, type, filename FROM msgmeta WHERE chat = :chat AND grouped_id = :grouped_id");
        $stmt->execute([':chat' => $channel, ':grouped_id' => $grouped_id]);

        $result = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $row['is_cover'] = stripos($row['filename'], 'cover') !== false;
            $result[$row['msgid']] = $row;
        }
        return $result;
    }

    public function checkFileOverAllChannels($channels, $meta, $msgid, $source, $topic = null, $forcedTopic = null): void
    {
        foreach ($channels as $target => $props) {
            $topicUsed = $forcedTopic ?? $props['topic'] ?? null;

            if ($this->syncCheckTargetChannel($target, $meta)) {
                $this->logger->info("[{$source} → {$target}] Media already present. Skipping.");
                continue;
            }

            $key = $this->buildToForwardKey($source, $target, $msgid, $topicUsed);
            if (isset($this->toForward[$key])) {
                $this->logger->info("[{$source} → {$target}] Already queued. Skipping.");
                continue;
            }

            $payload = [
                'from' => $source,
                'to' => $target,
                'msgid' => $msgid,
                'topic' => $topicUsed
            ];

            $this->fillForwardArray($source, $target, $msgid, $payload, $topicUsed);
            $this->logger->info("[{$source} → {$target}] Queued for forwarding.");
        }
    }
}
