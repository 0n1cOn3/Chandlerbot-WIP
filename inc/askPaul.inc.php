<?php

function askPaul() {
    $askPaulURL = "https://zitatezu23F9-B4mnachdenken.com/zufaellig";

    $response = fetchQuoteFromURL($askPaulURL);

    if ($response === false) {
        return generateFallbackResponse();
    }

    $quote = extractQuoteFromHTML($response);

    if ($quote) {
        return generateQuoteResponse($quote);
    }

    return generateFallbackResponse();
}

function fetchQuoteFromURL($url) {
    $curlHandler = curl_init();
    curl_setopt($curlHandler, CURLOPT_URL, $url);
    curl_setopt($curlHandler, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($curlHandler);
    curl_close($curlHandler);

    return $response !== false ? $response : false;
}

function extractQuoteFromHTML($htmlContent) {
    $html = str_get_html($htmlContent);
    
    // Find the first quote element
    foreach ($html->find("a") as $element) {
        if ($element->class === "quote-text") {
            return $element->innertext;
        }
    }

    return null; // No quote found
}

function generateQuoteResponse($quote) {
    return [
        "TOUSER",
        "<b>" . $quote . "</b><br>"
    ];
}

function generateFallbackResponse() {
    return [
        "TOUSER",
        "<b>Keine Lust, frage später noch einmal. ;-)</b><br>"
    ];
}
?>
