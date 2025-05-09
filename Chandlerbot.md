```
  oooooooo8 oooo                                     oooo o888
o888     88  888ooooo    ooooooo   oo oooooo    ooooo888   888  ooooooooo8 oo oooooo
888          888   888   ooooo888   888   888 888    888   888 888oooooo8   888    888
888o     oo  888   888 888    888   888   888 888    888   888 888          888
 888oooo88  o888o o888o 88ooo88 8o o888o o888o  88ooo888o o888o  88oooo888 o888o

                              oooo                     o8                              
                               888ooooo     ooooooo  o888oo                            
                               888    888 888     888 888                              
                               888    888 888     888 888                              
                              o888ooo88     88ooo88    888o                            
```

                                                                                 
Telegram UserBot - Next Generation File Management Bot

# SYSTEM REQUIREMENTS
- PostgreSQL
- PHP 8.3 with PHP Composer, PostgreSQL Module, YAML Module
- PostgreSQL Server
- Web Server (Apache2)
- Git

# IMPORTANT NOTICE

Telegram allows only 2000 requests per hour!
Please configure your `channels.conf` file carefully.
A flood wait will alter the behavior of your regular Telegram client(s).
You won't be able to send messages, view pictures, videos, or forward messages.
In short, you won't be able to do anything. :-/

## CAUTION: NEW PHONE NUMBER

If the phone number is brand new, it will be closely monitored by Telegram for
potential abuse. It may even be flagged as a bad user due to previous actions
associated with the number (this is common with VoIP or easily acquired online
numbers, so expect a rapid ban).

To mitigate this, consider using your new phone number with an official Telegram
client and behaving like a normal user for
some weeks or months before utilizing the bot.

##  MAXIMUM MESSAGE LIMITS PER CHANNEL
1,006,000

# CONFIGURATION

1. Edit `conf/chandlerbot.conf` to configure database settings,
recover the forward queue, and set bot commands.

2. Edit `conf/channels.conf`:

# Simple Section Configuration

```bash
uniqueName123:
  from: channelid1
  to:
    - channelid2 
```
## SETUP INSTRUCTIONS

Prepare System (once):
For bare-metal installation:

```bash
./prepare_env.sh
```

# What is new?

**1.0.3**
> Release on Github, looking for helpers and testers :D
> Fixed some PHP and database related issues
  
**1.0.2**
> Skipped...
  
**1.0.1**
> Refactored complete source code from scratch with todays PHP and JS/HTML Standard.
