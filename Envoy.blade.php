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
    simulate_failure
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

@task('simulate_failure')
    echo 'Simulating failure'
    false
@endtask

@task('run_optimize')
    echo 'Running optimization commands'
    cd {{ $new_release_dir }}
    php artisan optimize:clear
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


<!-- rollback -->
@task('rollback')
    echo "Starting rollback process"
    cd {{ $app_dir }}

    # Cek apakah ada symlink current
    if [ ! -L {{ $app_dir }}/current ]; then
        echo "No current release found. Rollback aborted."
        exit 1
    fi

    # Ambil rilis saat ini
    current_release=$(readlink -f {{ $app_dir }}/current)
    echo "Current release: $(basename $current_release)"

    # Ambil rilis terbaru (yang gagal)
    latest_release=$(ls -dt {{ $releases_dir }}/* | head -n 1)

    if [ -z "$latest_release" ]; then
        echo "No latest release found. Rollback aborted."
        exit 1
    fi

    echo "Latest failed release: $(basename $latest_release)"

    # Cek status migrasi untuk current release
    echo "Checking migration status for current release"
    current_migrated_count=$(cd $current_release && php artisan migrate:status | grep '\[1\]' | wc -l)

    # Cek status migrasi untuk latest failed release
    echo "Checking migration status for latest failed release"
    latest_migrated_count=$(cd $latest_release && php artisan migrate:status | grep '\[1\]' | wc -l)

    echo "Current release migrations count: $current_migrated_count"
    echo "Latest failed release migrations count: $latest_migrated_count"

    # Hapus symlink current
    rm {{ $app_dir }}/current

    # Buat symlink ke rilis sebelumnya
    ln -s $latest_release {{ $app_dir }}/current

    # Jika jumlah migrasi di current release lebih banyak dari latest release, lakukan rollback
    if [ "$current_migrated_count" -gt "$latest_migrated_count" ]; then
        echo "Rolling back last batch of migrations"
        php artisan migrate:rollback --force
    else
        echo "No migrations to rollback, current migrations match or are fewer than latest release"
    fi

    # Bersihkan cache
    echo "Clearing application cache"
    php artisan cache:clear
    php artisan config:clear
    php artisan view:clear

    # Restart PHP-FPM
    echo "Restarting PHP-FPM"
    sudo systemctl restart php8.3-fpm

    # Hapus rilis yang gagal
    echo "Removing failed release: $(basename $latest_release)"
    rm -rf $latest_release

    echo "Rollback completed successfully"
@endtask
