<?php

/**
 * This file is part of the Latte (https://latte.nette.org)
 * Copyright (c) 2008 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Bridges\ApplicationLatte\Nodes;

use Latte\Compiler\Block;
use Latte\Compiler\Nodes\AreaNode;
use Latte\Compiler\Nodes\Php\Scalar;
use Latte\Compiler\Nodes\StatementNode;
use Latte\Compiler\PrintContext;
use Latte\Compiler\Tag;
use Latte\Compiler\TemplateParser;
use Latte\Runtime\Template;
use Nette\Bridges\ApplicationLatte\SnippetDriver;


/**
 * {snippetArea [name]}
 */
class SnippetAreaNode extends StatementNode
{
	public Block $block;
	public AreaNode $content;


	/** @return \Generator<int, ?array, array{AreaNode, ?Tag}, static> */
	public static function create(Tag $tag, TemplateParser $parser): \Generator
	{
		$node = new static;
		$name = $tag->parser->parseUnquotedStringOrExpression();
		$node->block = new Block($name, Template::LayerSnippet, $tag);
		$parser->checkBlockIsUnique($node->block);
		[$node->content, $endTag] = yield;
		if ($endTag && $name instanceof Scalar\StringNode) {
			$endTag->parser->stream->tryConsume($name->value);
		}
		return $node;
	}


	public function print(PrintContext $context): string
	{
		$context->addBlock($this->block);
		$this->block->content = $context->format(
			<<<'XX'
				$this->global->snippetDriver->enter(%raw, %dump);
				try {
					%raw
				} finally {
					$this->global->snippetDriver->leave();
				}

				XX,
			$this->block->name,
			SnippetDriver::TypeArea,
			$this->content,
		);

		return $context->format(
			'$this->renderBlock(%raw, [], null, %dump) %line;',
			$this->block->name,
			Template::LayerSnippet,
			$this->startLine,
		);
	}


	public function &getIterator(): \Generator
	{
		yield $this->block->name;
		yield $this->content;
	}
}
