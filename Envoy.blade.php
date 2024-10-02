@servers(['web' => 'root@8.219.65.111'])

@setup
    $repository         = 'git@github.com:SantriKoding-com/CRUD-Laravel-11.git';
    $releases_dir       = '/var/www/app-github/releases';
    $app_dir            = '/var/www/app-github';
    $release            = date('YmdHis');
    $new_release_dir    = $releases_dir .'/'. $release;
@endsetup

@story('deploy')
    clone_repository
    run_composer
    create_cache_directory
    link_env_file
    generate_app_key
    handle_storage_directory
    run_migrations
    run_optimize
    update_symlinks
    delete_git_metadata
    clean_old_releases
    change_permission_owner
    restart_php
@endstory

@task('clone_repository')
    echo 'Cloning repository'
    [ -d {{ $releases_dir }} ] || mkdir {{ $releases_dir }}
    git clone --depth 1 {{ $repository }} {{ $new_release_dir }}
@endtask

@task('run_composer')
    echo "Starting deployment ({{ $release }})"
    cd {{ $new_release_dir }}
    echo "Running composer..."
    composer install --optimize-autoloader
@endtask

@task('create_cache_directory')
    echo 'Ensuring bootstrap and cache directories exist'
    mkdir -p {{ $new_release_dir }}/bootstrap/cache
    chown -R www-data:www-data {{ $new_release_dir }}/bootstrap/cache
    chmod -R 775 {{ $new_release_dir }}/bootstrap/cache

    echo 'Ensuring other necessary directories exist and writable'
    mkdir -p {{ $new_release_dir }}/storage/framework/views
    mkdir -p {{ $new_release_dir }}/storage/framework/sessions
    mkdir -p {{ $new_release_dir }}/storage/framework/cache
    chown -R www-data:www-data {{ $new_release_dir }}/storage
    chmod -R 775 {{ $new_release_dir }}/storage
@endtask

@task('link_env_file')
    echo 'Linking .env file'
    ln -nfs {{ $app_dir }}/.env {{ $new_release_dir }}/.env
@endtask

@task('generate_app_key')
    echo 'Checking for existing application key'
    
    # Periksa apakah APP_KEY sudah ada di .env
    if ! grep -q '^APP_KEY=' {{ $app_dir }}/.env; then
        echo 'Generating application key'
        cd {{ $app_dir }}/current  # Pindah ke direktori current
        php artisan key:generate
    else
        echo 'Application key already exists, skipping key generation'
    fi
@endtask

@task('handle_storage_directory')
    echo 'Handling storage directory'
    if [ ! -d {{ $app_dir }}/storage ]; then
        echo 'Creating storage directory in app_dir'
        cp -r {{ $new_release_dir }}/storage {{ $app_dir }}/storage
    else
        echo 'Preserving existing storage contents'
        rsync -a --delete {{ $app_dir }}/storage/ {{ $new_release_dir }}/storage/
    fi
    chown -R www-data:www-data {{ $app_dir }}/storage
    chmod -R 775 {{ $app_dir }}/storage
@endtask

@task('run_migrations')
    echo 'Running migrations'
    cd {{ $new_release_dir }}
    php artisan migrate --force
@endtask

@task('run_optimize')
    echo 'Running optimization commands'
    cd {{ $new_release_dir }}
    php artisan optimize:clear
@endtask

@task('run_migrations')
    echo 'Creating cache table if not exists and running migrations'
    cd {{ $new_release_dir }}
    php artisan cache:table
    php artisan migrate --force
@endtask

@task('update_symlinks')
    echo 'Linking storage directory'
    rm -rf {{ $new_release_dir }}/storage
    ln -nfs {{ $app_dir }}/storage {{ $new_release_dir }}/storage

    echo 'Linking current release'
    ln -nfs {{ $new_release_dir }} {{ $app_dir }}/current

    echo 'Linking storage:link'
    php {{ $new_release_dir }}/artisan storage:link
@endtask

@task('delete_git_metadata')
    echo 'Delete .git folder'
    cd {{ $new_release_dir }}
    rm -rf .git
@endtask

@task('change_permission_owner')
    echo 'Change Permission Owner'
    cd {{ $new_release_dir }}
    chown -R www-data:www-data .
@endtask

@task('clean_old_releases')
    # This will list our releases by modification time and delete all but the 2 most recent.
    purging=$(ls -dt {{ $releases_dir }}/* | tail -n +3);

    if [ "$purging" != "" ]; then
        echo Purging old releases: $purging;
        rm -rf $purging;
    else
        echo 'No releases found for purging at this time';
    fi
@endtask

@task('restart_php')
    echo 'Restarting php8.3-fpm'
    sudo systemctl restart php8.3-fpm
@endtask


{{-- @servers(['web' => 'root@8.219.65.111'])

@setup
    $repository         = 'git@github.com:SantriKoding-com/CRUD-Laravel-11.git';
    $releases_dir       = '/var/www/app-github/releases';
    $app_dir            = '/var/www/app-github';
    $release            = date('YmdHis');
    $new_release_dir    = $releases_dir .'/'. $release;
@endsetup

@story('deploy')
    clone_repository
    run_composer
    create_cache_directory
    run_deploy_scripts
    generate_app_key
    create_storage_directory
    run_migrations
    update_symlinks
    delete_git_metadata
    clean_old_releases
    change_permission_owner
    restart_php
@endstory

@task('clone_repository')
    echo 'Cloning repository'
    [ -d {{ $releases_dir }} ] || mkdir {{ $releases_dir }}
    git clone --depth 1 {{ $repository }} {{ $new_release_dir }}
@endtask

@task('run_composer')
    echo "Starting deployment ({{ $release }})"
    cd {{ $new_release_dir }}
    echo "Running composer..."
    composer install --optimize-autoloader
@endtask

@task('create_cache_directory')
    echo 'Ensuring bootstrap and cache directories exist'
    mkdir -p {{ $new_release_dir }}/bootstrap/cache
    chown -R www-data:www-data {{ $new_release_dir }}/bootstrap/cache
    chmod -R 775 {{ $new_release_dir }}/bootstrap/cache

    echo 'Ensuring other necessary directories exist and writable'
    mkdir -p {{ $new_release_dir }}/storage/framework/views
    mkdir -p {{ $new_release_dir }}/storage/framework/sessions
    mkdir -p {{ $new_release_dir }}/storage/framework/cache
    chown -R www-data:www-data {{ $new_release_dir }}/storage
    chmod -R 775 {{ $new_release_dir }}/storage
@endtask

@task('run_deploy_scripts')
    echo 'Linking .env file'
    ln -nfs {{ $app_dir }}/.env {{ $new_release_dir }}/.env

    echo 'Running deployment scripts'
    cd {{ $new_release_dir }}
    php artisan optimize:clear
@endtask

@task('generate_app_key')
    echo 'Checking for existing application key'
    
    # Periksa apakah APP_KEY sudah ada di .env
    if ! grep -q '^APP_KEY=' {{ $app_dir }}/.env; then
        echo 'Generating application key'
        cd {{ $app_dir }}/current  # Pindah ke direktori current
        php artisan key:generate
    else
        echo 'Application key already exists, skipping key generation'
    fi
@endtask

@task('create_storage_directory')
    echo 'Checking for existing storage directory in app_dir'
    if [ ! -d {{ $app_dir }}/storage ]; then
        echo 'Creating storage directory in app_dir'
        cp -r {{ $new_release_dir }}/storage {{ $app_dir }}/storage
        chown -R www-data:www-data {{ $app_dir }}/storage
        chmod -R 775 {{ $app_dir }}/storage
    else
        echo 'Storage directory already exists in app_dir, skipping creation.'
    fi
@endtask

@task('run_migrations')
    echo 'Running migrations'
    cd {{ $new_release_dir }}
    php artisan migrate --force
@endtask

@task('update_symlinks')
    echo 'Linking storage directory'
    # Hapus symlink lama jika ada, tapi jangan hapus folder storage yang sudah ada
    if [ -L {{ $new_release_dir }}/storage ]; then
        rm {{ $new_release_dir }}/storage
    fi
    ln -nfs {{ $app_dir }}/storage {{ $new_release_dir }}/storage

    echo 'Linking current release'
    ln -nfs {{ $new_release_dir }} {{ $app_dir }}/current

    echo 'Linking storage:link'
    php {{ $new_release_dir }}/artisan storage:link
@endtask


@task('delete_git_metadata')
    echo 'Delete .git folder'
    cd {{ $new_release_dir }}
    rm -rf .git
@endtask

@task('change_permission_owner')
    echo 'Change Permission Owner'
    cd {{ $new_release_dir }}
    chown -R www-data:www-data .
@endtask

@task('clean_old_releases')
    # This will list our releases by modification time and delete all but the 2 most recent.
    purging=$(ls -dt {{ $releases_dir }}/* | tail -n +3);

    if [ "$purging" != "" ]; then
        echo Purging old releases: $purging;
        rm -rf $purging;
    else
        echo 'No releases found for purging at this time';
    fi
@endtask

@task('restart_php')
    echo 'Restarting php8.3-fpm'
    sudo systemctl restart php8.3-fpm
@endtask --}}
