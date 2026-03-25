<?php
declare(strict_types=1);

use SuperKernel\Annotator\Provider\AnnotationCollectorProvider;
use SuperKernel\ComposerResolver\Provider\PackageCollectorProvider;
use SuperKernel\Contract\ApplicationInterface;
use SuperKernel\Di\Container;
use SuperKernel\PathResolver\Provider\PathResolverProvider;
use SuperKernel\ProcessHandler\Provider\ProcessHandlerProvider;
use SuperKernel\Reflector\Provider\ReflectionCollectorProvider;

require __DIR__ . '/../vendor/autoload.php';

$pathResolver = new PathResolverProvider()();
$processHandler = new ProcessHandlerProvider()();
$reflectionCollector = new ReflectionCollectorProvider()();

$packageCollector = new PackageCollectorProvider()(
	$pathResolver,
	$processHandler,
);

$annotationCollector = new AnnotationCollectorProvider()(
	$pathResolver,
	$processHandler,
	$packageCollector,
	$reflectionCollector,
);

$container = new Container($annotationCollector, $reflectionCollector);

$container->get(ApplicationInterface::class)->run();