#!/usr/bin/env php
<?php
declare(strict_types=1);

/*
 * Debug messages like this one show you the progress when you do
 * docker logs -f <container>
 */
debugMessage('Start of configure-db.php');

const CONFIGPATH ='/var/www/config.php';
$eport    = null;

# Initial configuration array:
$config = [
    'SELF_URL_PATH' => env('SELF_URL_PATH', 'http://localhost'),
    'DB_TYPE'       => 'pgsql',
    'DB_NAME'       => env('DB_NAME', 'ttrss'),
    'DB_USER'       => env('DB_USER', $config['DB_NAME']),
    'DB_PASS'       => env('DB_PASS', $config['DB_USER']),

];

debugMessage(sprintf('DB_NAME is "%s", DB_USER is "%s".', $config['DB_NAME'], $config['DB_USER']));

// optional extra debug message:
//debugMessage(sprintf('DB_PASS is "%s"', $config['DB_PASS']));

# If DB_TYPE is set, just use that one.
if (false !== getenv('DB_TYPE')) {
    debugMessage(sprintf('DB_TYPE is "%s"', getenv('DB_TYPE')));
    $config['DB_TYPE'] = getenv('DB_TYPE');
}

# Overrule if this env variable is set, because it tells us
# a postgres container is linked.
if (false !== getenv('DB_PORT_5432_TCP_ADDR')) {
    debugMessage('Detected a linked PostgreSQL instance.');
    $config['DB_TYPE'] = 'pgsql';
    $eport             = 5432;

}

# Overrule if this env variable is set, because it tells us
# a MySQL container is linked.
if (false !== getenv('DB_PORT_3306_TCP_ADDR')) {
    debugMessage('Detected a linked MySQL instance.');
    // mysql container linked
    $config['DB_TYPE'] = 'mysql';
    $eport             = 3306;
}

# If $eport is not null, set host and port from these environment variables.
if (null !== $eport) {
    debugMessage('Change config to use linked DB instance.');
    $config['DB_HOST'] = env(sprintf('DB_PORT_%d_TCP_ADDR', $eport));
    $config['DB_PORT'] = env(sprintf('DB_PORT_%s_TCP_PORT', $eport));
}

# if no DB_PORT in env, no port has been configured.
if (null === $eport && false === getenv('DB_PORT')) {
    error('The env DB_PORT does not exist. Make sure to run with "--link mypostgresinstance:DB"');
    // script exits here.
}

# numeric DB_PORT provided; assume port number passed directly
if (is_numeric(getenv('DB_PORT')) && false !== getenv('DB_HOST')) {
    debugMessage(sprintf('DB_HOST is "%s" and DB_PORT is %d', env('DB_HOST'), env('DB_PORT')));
    $config['DB_HOST'] = env('DB_HOST');
    $config['DB_PORT'] = env('DB_PORT');

    # determin DB type based on port.
    if (empty($config['DB_TYPE'])) {
        switch ($config['DB_PORT']) {
            case 3306:
                $config['DB_TYPE'] = 'mysql';
                break;
            case 5432:
                $config['DB_TYPE'] = 'pgsql';
                break;
            default:
                error('Database on non-standard port ' . $config['DB_PORT'] . ' but env DB_TYPE not present');
                // script exits here.
        }
    }
}
# Cannot connect to DB for some reason.
if (false === dbcheck($config)) {
    debugMessage('Database login failed, trying to create DB.');

    $super            = $config;
    $super['DB_NAME'] = null;
    $super['DB_USER'] = env('DB_ENV_USER', 'docker');
    $super['DB_PASS'] = env('DB_ENV_PASS', $super['DB_USER']);

    if (false === dbcheck($super)) {
        error('Database login failed. Could also not login with DB_ENV_USER and DB_ENV_PASS.');
        // script exits here.
    }
    debugMessage('Database login created and confirmed');

    $pdo = dbconnect($super);
    if ('mysql' === $super['DB_TYPE']) {
        $pdo->exec(sprintf('CREATE DATABASE %s', $config['DB_NAME']));
        $pdo->exec(
            sprintf(
                'GRANT ALL PRIVILEGES ON %s.* TO %s@"%%" IDENTIFIED BY %s', $config['DB_NAME'], $pdo->quote($config['DB_USER']), $pdo->quote($config['DB_PASS'])
            )
        );
    }
    if ('pgsql' === $super['DB_TYPE']) {
        $pdo->exec(sprintf('CREATE ROLE %s WITH LOGIN PASSWORD %s', $config['DB_USER'], $pdo->quote($config['DB_PASS'])));
        $pdo->exec(sprintf('CREATE DATABASE %s WITH OWNER %s', $config['DB_NAME'], $config['DB_USER']));
    }
    unset($pdo);
}

# Can connect to DB
if (true === dbcheck($config)) {
    $pdo = dbconnect($config);
    try {
        $pdo->exec('SELECT 1 FROM ttrss_feeds');
    } catch (PDOException $e) {
        debugMessage('Database table not found, applying schema... ');
        $schema = file_get_contents(sprintf('schema/ttrss_schema_%s.sql', $config['DB_TYPE']));
        $schema = preg_replace('/--(.*?);/', '', $schema);
        $schema = preg_replace('/[\r\n]/', ' ', $schema);
        $schema = trim($schema, ' ;');
        foreach (explode(';', $schema) as $stm) {
            $pdo->exec($stm);
        }
        unset($pdo);
    }
}
# preg replace config file.
$contents = file_get_contents(CONFIGPATH);
foreach ($config as $name => $value) {
    $contents = preg_replace('/(define\s*\(\'' . $name . '\',\s*)(.*)(\);)/', '$1"' . $value . '"$3', $contents);
}
file_put_contents(CONFIGPATH, $contents);

# install plugin:
mkdir('/var/www/plugins.local/tumblr_gdpr_ua');
file_put_contents('/var/www/plugins.local/tumblr_gdpr_ua/init.php', file_get_contents('https://raw.githubusercontent.com/hkockerbeck/ttrss-tumblr-gdpr-ua/master/init.php'));
file_put_contents('/var/www/plugins.local/tumblr_gdpr_ua/pref_template.html', file_get_contents('https://raw.githubusercontent.com/hkockerbeck/ttrss-tumblr-gdpr-ua/master/pref_template.html'));

/**
 * @param string $name
 * @param null   $default
 *
 * @return string|null
 */
function env(string $name, $default = null): ?string
{
    $v = getenv($name) ?: $default;

    if (null === $v) {
        error('The env ' . $name . ' does not exist');
    }

    return $v;
}

/**
 * @param string $text
 */
function error(string $text): void
{
    echo 'Error: ' . $text . PHP_EOL;
    exit(1);
}

/**
 * @param array $config
 *
 * @return PDO
 */
function dbconnect(array $config): PDO
{
    $map = ['host' => 'HOST', 'port' => 'PORT', 'dbname' => 'NAME'];
    $dsn = $config['DB_TYPE'] . ':';
    foreach ($map as $d => $h) {
        if (isset($config[sprintf('DB_%s', $h)])) {
            $dsn .= $d . '=' . $config[sprintf('DB_%s', $h)] . ';';
        }
    }
    $pdo = new PDO($dsn, $config['DB_USER'], $config['DB_PASS']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    return $pdo;
}

/**
 * @param array $config
 *
 * @return bool
 */
function dbcheck(array $config): bool
{
    try {
        dbconnect($config);

        return true;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * @param string $message
 */
function debugMessage(string $message): void {
    echo sprintf('%s%s', $message, PHP_EOL);
}

