<?php

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


$latte = new Latte\Engine;
$latte->setLoader(new Latte\Loaders\StringLoader);
$latte->addExtension(new Nette\Bridges\ApplicationLatte\UIExtension);

$template = <<<'EOD'
		<div class="test" n:snippet="outer">
		<p>Outer</p>
		</div>

		<div n:snippet="gallery" class="{=class}"></div>

	EOD;

Assert::matchFile(
	__DIR__ . '/expected/snippet.n.phtml',
	$latte->compile($template),
);
