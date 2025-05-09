# Chandlerbot

## Description

**ATTENTION**

This branch is still WIP. It is not ready to be used and needs bug fixes!

**Chandlerbot** is a flexible bot that offers various features such as database integration, file forwarding and interactivity with users via Telegram. With a customizable configuration and PostgreSQL support, the bot is ideal for automating file and communication processes.

---

### Requirements
- **PostgreSQL**: Installed and configured.
- **Apache Webserver**: For the integration (optional).
- **Telegram Account**

### Directory structure
- A download folder: `downloads` (relative to the bot's directory).

---

## Installation

1. **Clone repository**:
   ```bash
   git clone https://github.com/0n1cOn3/Chandlerbot.git
   cd Chandlerbot
   ```

2. set up **database**:
   - Create a PostgreSQL database with the default values from ``Chandlerbot.conf`` or customize it.

3. customize **configuration files**:
   - Edit `Chandlerbot.conf` and `channels.conf` to customize the settings accordingly (see below).

---

## Configuration files

### `Chandletbot.conf`

#### Important parameters
- **Botcommand**: Command for bot interactions. Default: `Chandler`.
- **DB connection**: Customize the following fields:
  - `dbhost`: IP address of the database server (default: `127.0.0.1`).
  - `dbport`: Port of the database (default: `5432`).
