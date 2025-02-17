<?php

/**
 * This file is part of the Latte (https://latte.nette.org)
 * Copyright (c) 2008 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Bridges\ApplicationLatte\Nodes;

use Latte\CompileException;
use Latte\Compiler\Block;
use Latte\Compiler\Nodes\AreaNode;
use Latte\Compiler\Nodes\AuxiliaryNode;
use Latte\Compiler\Nodes\Html\ElementNode;
use Latte\Compiler\Nodes\Php\Expression\AssignNode;
use Latte\Compiler\Nodes\Php\Expression\VariableNode;
use Latte\Compiler\Nodes\Php\Scalar;
use Latte\Compiler\Nodes\StatementNode;
use Latte\Compiler\PrintContext;
use Latte\Compiler\Tag;
use Latte\Compiler\TemplateParser;
use Latte\Runtime\Template;
use Nette\Bridges\ApplicationLatte\SnippetDriver;


/**
 * {snippet [name]}
 */
class SnippetNode extends StatementNode
{
	public static string $snippetAttribute = 'id';
	public Block $block;
	public AreaNode $content;
	public ?ElementNode $htmlElement;


	/** @return \Generator<int, ?array, array{AreaNode, ?Tag}, static> */
	public static function create(Tag $tag, TemplateParser $parser): \Generator
	{
		$tag->outputMode = $tag::OutputRemoveIndentation;
		$node = new static;
		$node->htmlElement = $tag->isNAttribute() ? $tag->htmlElement : null;

		if ($tag->parser->isEnd()) {
			$name = null;
			$node->block = new Block(new Scalar\StringNode(''), Template::LayerSnippet, $tag);
		} else {
			$name = $tag->parser->parseUnquotedStringOrExpression();
			$node->block = new Block($name, Template::LayerSnippet, $tag);
			if (!$node->block->isDynamic()) {
				$parser->checkBlockIsUnique($node->block);
			}
		}

		if ($tag->isNAttribute()) {
			if ($tag->prefix !== $tag::PrefixNone) {
				throw new CompileException("Use n:snippet instead of {$tag->getNotation()}", $tag->startLine);

			} elseif ($tag->htmlElement->getAttribute(self::$snippetAttribute)) {
				throw new CompileException('Cannot combine HTML attribute ' . self::$snippetAttribute . ' with n:snippet.', $tag->startLine);

			} elseif (isset($tag->htmlElement->nAttributes['ifcontent'])) {
				throw new CompileException('Cannot combine n:ifcontent with n:snippet.', $tag->startLine);

			} elseif (isset($tag->htmlElement->nAttributes['foreach'])) {
				throw new CompileException('Combination of n:snippet with n:foreach is invalid, use n:inner-foreach.', $tag->startLine);
			}

			$tag->replaceNAttribute(new AuxiliaryNode(
				fn(PrintContext $context) => "echo ' " . $node->printAttribute($context) . "';",
			));
		}

		[$node->content, $endTag] = yield;
		if ($endTag && $name instanceof Scalar\StringNode) {
			$endTag->parser->stream->tryConsume($name->value);
		}

		return $node;
	}


	public function print(PrintContext $context): string
	{
		$dynamic = $this->block->isDynamic();
		if (!$dynamic) {
			$context->addBlock($this->block);
		}

		$snippetContent = $context->format(
			<<<'XX'
				$this->global->snippetDriver->enter(%raw, %dump) %line;
				try {
					%raw
				} finally {
					$this->global->snippetDriver->leave();
				}

				XX,
			$dynamic ? '$ʟ_nm' : $this->block->name,
			$dynamic ? SnippetDriver::TypeDynamic : SnippetDriver::TypeStatic,
			$this->startLine,
			$this->htmlElement->content ?? $this->content,
		);

		if (!$dynamic) {
			$this->block->content = $snippetContent;
			$snippetContent = $context->format(
				'$this->renderBlock(%raw, [], null, %dump) %line;',
				$this->block->name,
				Template::LayerSnippet,
				$this->startLine,
			);
		}

		if ($this->htmlElement) {
			try {
				$saved = $this->htmlElement->content;
				$this->htmlElement->content = new AuxiliaryNode(fn() => $snippetContent);
				return $this->htmlElement->print($context);
			} finally {
				$this->htmlElement->content = $saved;
			}
		} else {
			return <<<XX
				echo '<div {$this->printAttribute($context)}>';
				{$snippetContent}
				echo '</div>';
				XX;
		}
	}


	private function printAttribute(PrintContext $context): string
	{
		return $context->format(
			<<<'XX'
				%raw="', htmlspecialchars($this->global->snippetDriver->getHtmlId(%raw)), '"
				XX,
			self::$snippetAttribute,
			$this->block->isDynamic()
				? new AssignNode(new VariableNode('ʟ_nm'), $this->block->name)
				: $this->block->name,
		);
	}


	public function &getIterator(): \Generator
	{
		yield $this->block->name;
		yield $this->content;
	}
}
