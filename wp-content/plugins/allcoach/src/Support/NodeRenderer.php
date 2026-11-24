<?php

declare(strict_types=1);

namespace AllCoach\Support;

defined( 'ABSPATH' ) || exit;


class NodeRenderer {

	private array $nodeHandlers = [];
	private array $markHandlers = [];

	public function node( string $type, callable $handler ): static {
		$this->nodeHandlers[ $type ] = $handler;
		return $this;
	}

	public function mark( string $type, callable $handler ): static {
		$this->markHandlers[ $type ] = $handler;
		return $this;
	}

	public function render( string|array $doc ): string {
		if ( is_string( $doc ) ) {
			$doc = json_decode( $doc, true ) ?? [
				'type'    => 'doc',
				'content' => [],
			];
		}
		return $this->renderNode( $doc );
	}

	public function renderNode( array $node ): string {
		$type = $node['type'] ?? '';

		if ( $type === 'text' ) {
			return $this->renderText( $node );
		}

		$attrs    = $node['attrs'] ?? [];
		$children = fn() => $this->renderChildren( $node['content'] ?? [] );

		if ( isset( $this->nodeHandlers[ $type ] ) ) {
			return ( $this->nodeHandlers[ $type ] )( $node, $attrs, $children );
		}

		return $children();
	}

	public function renderChildren( array $nodes ): string {
		return implode( '', array_map( fn( $n ) => $this->renderNode( $n ), $nodes ) );
	}

	private function renderText( array $node ): string {
		$text = esc_html( $node['text'] ?? '' );
		return $this->applyMarks( $text, $node['marks'] ?? [] );
	}

	private function applyMarks( string $text, array $marks ): string {
		foreach ( $marks as $mark ) {
			$markType = $mark['type'] ?? '';
			if ( isset( $this->markHandlers[ $markType ] ) ) {
				$text = ( $this->markHandlers[ $markType ] )( $text, $mark['attrs'] ?? [] );
			}
		}
		return $text;
	}
}
