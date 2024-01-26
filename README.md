# The Defrag Racing Project

This is an early attempt to rewrite defrag racing in Laravel, this will help improve the speed of development, and allow us to add more new features in very little time.

## Automated Deployment
Just running the `deploy.py` script in the `deploy` folder should automatically deploy a new release and do all the caching and configuration.

```
python3 deploy.py
```

## Deployment Explained
The folder structure for deployment is as follows:
```
-defrag-racing-project
    --production
        --deploy
        --releases
        --current

    
    --staging
        --deploy
        --releases
        --current
```

The `deploy` folder contains all the static files that should't change with deployment.
Mainly, the `.env` file and the `storage` folder.
It can also contain the `deploy.py` file along with the `github.txt` file.

The `releases` folder is where all versions live. Every deployment, a new clone is made in the releases folder.

The `current` is a symlink to the latest `releases` clone. and the nginx will host the `current` symlink.
Meaning by simply changing the `current` symlink, we can make the website run on a newer version, without any downtime at all.

#### Other Notes
- A `.env` and a `storage` symlinks must be created in every `releases` clone, and they should point to the `deploy` folder.
- Because of the folder structure, we should be able to deploy more than just one version (e.g production, staging, testing, beta, etc...)