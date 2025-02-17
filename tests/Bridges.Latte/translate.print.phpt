<?php

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


$latte = new Latte\Engine;
$latte->setLoader(new Latte\Loaders\StringLoader);
$latte->addExtension(new Nette\Bridges\ApplicationLatte\UIExtension);

Assert::contains(
	'echo LR\Filters::escapeHtmlText(($this->filters->translate)(\'var\')) /*',
	$latte->compile('{_var}'),
);

Assert::contains(
	'echo LR\Filters::escapeHtmlText(($this->filters->filter)(($this->filters->translate)(\'var\'))) /*',
	$latte->compile('{_var|filter}'),
);
