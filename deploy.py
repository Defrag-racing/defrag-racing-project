import os
import subprocess

PROJECT_PATH = "/var/www/defrag-racing-project/production"
REPOSITORY_URL = "https://github.com/Defrag-racing/defrag-racing-project.git"
PROJECT_NAME = "defrag-racing-project"

def get_next_id():
	result = 0
	for file in os.listdir(f"{PROJECT_PATH}/releases"):
		if file.startswith(PROJECT_NAME) == False:
			continue

		id = int(file.split('-')[-1])

		if id > result:
			result = id

	return result + 1

def get_next_release_name():
    id = get_next_id()

    return PROJECT_NAME + "-" + str(id)

def get_git_clone_cmd(name):
    return f"git clone {REPOSITORY_URL} {name}"

def pipeline_cmds(name):
    cmds = [
        "composer install --optimize-autoloader --no-dev --no-interaction",
        "npm install",
        "npm run build",
        f"ln -s {PROJECT_PATH}/deploy/.env {PROJECT_PATH}/releases/{name}/.env",
        f"rm -rdf {PROJECT_PATH}/releases/{name}/storage",
        f"ln -s {PROJECT_PATH}/deploy/storage {PROJECT_PATH}/releases/{name}/storage",
        "php artisan storage:link",
        "php artisan filament:assets",
        "php artisan config:cache",
        "php artisan route:cache",
        "php artisan view:cache",
        "php artisan icons:cache",
        f"rm {PROJECT_PATH}/current",
        f"ln -s {PROJECT_PATH}/releases/{name} {PROJECT_PATH}/current",
        'supervisorctl restart "defrag-racing-octane:*"',
        "php artisan octane:reload",
        "php artisan queue:restart"
    ]

    return cmds

def deploy():
    name = get_next_release_name()

    git_clone_cmd = get_git_clone_cmd(name)

    cmds = pipeline_cmds(name)

    subprocess.run(git_clone_cmd, shell=True, cwd=f"{PROJECT_PATH}/releases")

    for cmd in cmds:
        subprocess.run(cmd, shell=True, cwd=f"{PROJECT_PATH}/releases/{name}")

if __name__ == "__main__":
    deploy()

