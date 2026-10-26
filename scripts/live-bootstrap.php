<?php

declare(strict_types=1);

/** Bootstrap for explicit billable conformance tests; never changes the test application. */
$externalAutoload = getenv('AISDK_LIVE_AUTOLOAD');
if (is_string($externalAutoload) && $externalAutoload !== '') {
    require $externalAutoload;
}

$environmentDirectory = getenv('AISDK_LIVE_ENV_DIR');
if (is_string($environmentDirectory) && $environmentDirectory !== '') {
    if (! class_exists(Dotenv\Dotenv::class)) {
        throw new RuntimeException('Loading a .env file requires an external application autoloader with vlucas/phpdotenv. Alternatively supply environment variables directly.');
    }
    Dotenv\Dotenv::createImmutable($environmentDirectory)->safeLoad();
}

foreach (['XAI_API_KEY', 'CONCENTRATE_API_KEY'] as $name) {
    $value = getenv($name);
    if (is_string($value) && $value !== '') {
        $_ENV[$name] = $value;
    }
}

$runtime = require dirname(__DIR__) . '/vendor/autoload.php';
$runtime->unregister();
$runtime->register(true);

$workspace = dirname(__DIR__, 2);
$loader = new Composer\Autoload\ClassLoader();
$loader->addPsr4('AiSdk\\', $workspace . '/core/src');
foreach (['openai' => 'OpenAI', 'openai-compatible' => 'OpenAICompatible', 'xai' => 'XAI', 'concentrate' => 'Concentrate'] as $directory => $namespace) {
    $loader->addPsr4('AiSdk\\' . $namespace . '\\', $workspace . '/' . $directory . '/src');
    if (is_dir($workspace . '/' . $directory . '/src/Root')) {
        $loader->addPsr4('AiSdk\\', $workspace . '/' . $directory . '/src/Root');
    }
}
$loader->register(true);
