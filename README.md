<h1 align="center">
  <a href="https://defrag.racing">
    <img alt="defrag.racing" src="public/images/logo.png" width="250">
  </a>
</h1>

<h4 align="center">
  A modern, community-driven hub for Quake III Arena Defrag enthusiasts.
</h4>

<p align="center">
  <a href="https://www.gnu.org/licenses/agpl-3.0"><img alt="License: AGPL v3" src="https://img.shields.io/badge/License-AGPL%20v3-blue.svg?style=for-the-badge"></a>
  <a href="https://discord.defrag.racing/"><img alt="Chat On Discord" src="https://img.shields.io/badge/chat-on%20discord-7289da?style=for-the-badge&logo=discord&logoColor=white"></a>
</p>

<p align="center">
  <a href="#sparkles-about">About</a> •
  <a href="#hammer_and_wrench-getting-started">Getting Started</a> •
  <a href="#heart-support-us">Support Us</a> •
  <a href="#handshake-contribution">Contribution</a> •
  <a href="#scroll-license">License</a>
</p>

## :sparkles: About

[DeFRaG](https://en.wikipedia.org/wiki/DeFRaG) is a game mode within Quake III Arena that challenges players to complete levels as quickly as possible using advanced movement techniques like strafe-jumping and rocket-jumping to achieve the fastest times.

The goal of [defrag.racing](https://defrag.racing) is to refresh the game by adding a modern and user-friendly UI, as well as introducing new features such as custom tournaments, rankings, clans, notifications and downloadable bundles with all necessary files to start playing DeFRaG as soon as possible.

## :hammer_and_wrench: Getting Started

> :warning: **Important Notice**: The installation steps provided in this guide have only been tested on a Linux machine. They are not thoroughly tested and are primarily intended as a kickstart for development. If you encounter any issues or if you have a better solution, we warmly invite you to contribute to this repository. Your contributions are highly appreciated!

> :warning: **Important Notice**: The following steps are designed to work with Docker, utilizing a Laravel tool called Sail. However, it's important to be aware that the production server operates on a **non-containerized** approach at the moment.

### Prerequisites

To clone and run this application, you'll need:
* [Git](https://git-scm.com)
* [Docker](https://docker.com/)
    - [Configure Docker to start on boot](https://docs.docker.com/engine/install/linux-postinstall/#configure-docker-to-start-on-boot-with-systemd)
    - [Add user to docker group](https://docs.docker.com/engine/install/linux-postinstall/#manage-docker-as-a-non-root-user)
* PHP:
    - You php and some extenstions:
        - php
        - php-curl
        - php-zip
        - php-xml
        - php-intl
    - In PHP `.ini` file you may need to uncomment extenstions:
        - filetype
        - zip
* [Composer](https://getcomposer.org)

### Installation

1. **Clone the repository**:
    - Using HTTPS:
        ```bash
        git clone https://github.com/neyoneit/defrag-racing-project.git
        ```
    - Using SSH:
        ```bash
        git clone git@github.com:neyoneit/defrag-racing-project.git
        ```

2. **Enter the repository**:

    All below commands assume you are in project's top level directory. Enter it with:
    ```bash
    cd defrag-racing-project
    ```

2. **Run startup script**:

    Script below contains all steps to setup, install and start local server.
    ```bash
    ./local_devel/start_local_server.sh
    ```

4. **Go to [localhost](http://localhost)**

5. **(Optionally) Load up dummy data**:

    You can load dummy data into your local environment using the following command:
    ```bash
    ./local_devel/load_dummy_data.sh
    ```
    The dummy SQL database includes an admin user with the following credentials:
    ```
    login: admin
    password: password
    ```
    After logging in, you can access the dashboard at [localhost/defraghq](http://localhost/defraghq).

6. **To stop the server run**:
    ```bash
    ./vendor/bin/sail stop
    ```

## :heart: Support Us

We hope you've enjoyed using using defrag.racing.

If you'd like to contribute to the ongoing development and maintenance of this project, as well as our other initiatives like defraglive and demome, please consider making a donation.

You can do so through [![PayPal Donation](https://img.shields.io/badge/PayPal-00457C?style=for-the-badge&logo=paypal&logoColor=white)](https://www.paypal.com/donate/?hosted_button_id=WH6GY4PDGU8FA).

Your support allows us to continue improving these projects for the benefit of all Defragers. Thank you!

## :handshake: Contribution

Even if you're not a developer, there are numerous ways you can contribute to this project. Please see the [CONTRIBUTION](CONTRIBUTING.md) guide for more information on how you can help. 

Thank you for considering contributing to defrag.racing. We appreciate it! :heart:

## :scroll: License

This project is licensed under the [GNU Affero General Public License v3 (AGPLv3)](LICENSE), which applies to all past and future contributions.

---

<h6 align="center">
  <a href="https://defrag.racing">defrag.racing</a> &nbsp;&middot;&nbsp;
  <a href="https://discord.defrag.racing/">discord</a> &nbsp;&middot;&nbsp;
  <a href="https://www.youtube.com/@DefragLegends">youtube</a> &nbsp;&middot;&nbsp;
  <a href="https://www.twitch.tv/defraglive">twitch</a>
</h6>

