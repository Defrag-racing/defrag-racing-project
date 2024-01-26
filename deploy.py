import os
import subprocess

PROJECT_PATH = "/var/www/defrag-racing-project/production"
REPOSITORY = "github.com/neyoneit/defrag-racing-project"
PROJECT_NAME = "defrag-racing-project"

def get_github_token():
    data = ''
    with open('github.txt', 'r') as file:
        data = file.read().replace('\n', '')

    return data

def get_id():
	result = 0
	for file in os.listdir(f"{PROJECT_PATH}/releases"):
		if file.startswith(PROJECT_NAME) == False:
			continue

		id = int(file.split('-')[-1])

		if id > result:
			result = id

	return result + 1

def get_name():
    id = get_id()

    return PROJECT_NAME + "-" + str(id)

def get_git_cmd(name):
    token = get_github_token()

    url = "https://" + token + "@" + REPOSITORY

    # return ['cd', f"{PROJECT_PATH}/releases", '&&', 'git', 'clone', url, name]
    return f"git clone {url} {name}"

def pipeline_cmds(name):
    cmds = [
        "composer install --optimize-autoloader --no-dev --no-interaction",
        "npm install",
        "npm run build",
        f"ln -s {PROJECT_PATH}/deploy/.env {PROJECT_PATH}/releases/{name}/.env",
        f"rm -rdf {PROJECT_PATH}/releases/{name}/storage",
        f"ln -s {PROJECT_PATH}/deploy/storage {PROJECT_PATH}/releases/{name}/storage",
        f"ln -s {PROJECT_PATH}/deploy/frankenphp {PROJECT_PATH}/releases/{name}/frankenphp",
        "php artisan storage:link",
        "php artisan filament:assets",
        "php artisan config:cache",
        "php artisan route:cache",
        "php artisan view:cache",
        "php artisan icons:cache",
        f"rm {PROJECT_PATH}/current",
        f"ln -s {PROJECT_PATH}/releases/{name} {PROJECT_PATH}/current",
        "php artisan octane:reload",
        "php artisan queue:restart"
    ]

    return cmds

def deploy():
    name = get_name()

    clone = get_git_cmd(name)

    cmds = pipeline_cmds(name)

    subprocess.run(clone, shell=True, cwd=f"{PROJECT_PATH}/releases")

    for cmd in cmds:
        subprocess.run(cmd, shell=True, cwd=f"{PROJECT_PATH}/releases/{name}")

if __name__ == "__main__":
    deploy()