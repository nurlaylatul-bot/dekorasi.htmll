<?php

declare(strict_types=1);

namespace AllCoach\Frontend;

defined( 'ABSPATH' ) || exit;

use AllCoach\Domain\Activity\ActivityField;
use AllCoach\Support\NodeRenderer;

class ActivityContentRenderer extends NodeRenderer {

	private ActivityFieldRenderer $fieldRenderer;

	public function __construct(
		private readonly array $savedValues = [],
		private readonly bool $isCompleted = false,
	) {
		$this->fieldRenderer = new ActivityFieldRenderer();
		$this->registerDefaults();
	}

	public static function make( array $savedValues = [], bool $isCompleted = false ): static {
		return new static( $savedValues, $isCompleted );
	}


	private function registerDefaults(): void {
		$this->registerMarks();
		$this->registerCoreNodes();
		$this->registerFormNodes();
		$this->registerMediaNodes();
	}

	private function registerMarks(): void {
		$this->mark( 'bold', static fn( string $t ) => '<strong>' . $t . '</strong>' );
		$this->mark( 'italic', static fn( string $t ) => '<em>' . $t . '</em>' );
		$this->mark( 'underline', static fn( string $t ) => '<u>' . $t . '</u>' );
		$this->mark( 'strike', static fn( string $t ) => '<s>' . $t . '</s>' );

		$this->mark(
			'link',
			static function ( string $t, array $a ): string {
				$href   = esc_url( $a['href'] ?? '' );
				$target = ! empty( $a['target'] ) ? ' target="' . esc_attr( $a['target'] ) . '" rel="noopener noreferrer"' : '';
				return '<a href="' . $href . '"' . $target . '>' . $t . '</a>';
			}
		);
	}
	private function registerCoreNodes(): void {
		$this->node( 'doc', static fn( $_n, $_a, $c ) => $c() );
		$this->node( 'paragraph', static fn( $_n, $_a, $c ) => '<p class="ac-act-text">' . $c() . '</p>' );
		$this->node( 'bulletList', static fn( $_n, $_a, $c ) => '<ul class="ac-act-list ac-act-list--unordered">' . $c() . '</ul>' );
		$this->node( 'orderedList', static fn( $_n, $_a, $c ) => '<ol class="ac-act-list ac-act-list--ordered">' . $c() . '</ol>' );
		$this->node( 'listItem', static fn( $_n, $_a, $c ) => '<li class="ac-act-list__item">' . $c() . '</li>' );
		$this->node( 'blockquote', static fn( $_n, $_a, $c ) => '<blockquote class="ac-act-blockquote">' . $c() . '</blockquote>' );
		$this->node( 'codeBlock', static fn( $_n, $_a, $c ) => '<pre><code>' . $c() . '</code></pre>' );
		$this->node( 'horizontalRule', static fn() => '<hr class="ac-act-hr">' );
		$this->node( 'hardBreak', static fn() => '<br class="ac-act-br">' );

		$this->node(
			'heading',
			static function ( $_n, array $a, callable $c ): string {
				$level = max( 1, min( 6, (int) ( $a['level'] ?? 1 ) ) );
				return "<h{$level} class=\"ac-act-heading ac-act-heading--{$level}\">" . $c() . "</h{$level}>";
			}
		);
	}

	private function registerFormNodes(): void {
		$this->node( 'shortAnswer', fn( $_n, array $a ) => $this->renderFormField( 'short_text', $a ) );
		$this->node( 'longAnswer', fn( $_n, array $a ) => $this->renderFormField( 'long_text', $a ) );
		$this->node( 'multipleChoice', fn( $_n, array $a ) => $this->renderFormField( 'multiple_choice', $a ) );
		$this->node( 'checkbox', fn( $_n, array $a ) => $this->renderFormField( 'checkbox', $a ) );
		$this->node( 'date', fn( $_n, array $a ) => $this->renderFormField( 'date', $a ) );

		$this->node(
			'fileUpload',
			fn( $_n, array $a ) => $this->renderFormField( 'file', $a, [ 'accept' => $a['accept'] ?? '' ] )
		);
	}

	private function renderFormField( string $type, array $attrs, array $extra = [] ): string {
		$label   = (string) ( $attrs['label'] ?? '' );
		$fieldId = $attrs['id'] ?? ( $label !== '' ? sanitize_key( $label ) : uniqid( 'field_', false ) );

		$field = ActivityField::fromArray(
			array_merge(
				[
					'type'     => $type,
					'label'    => $label,
					'required' => (bool) ( $attrs['required'] ?? false ),
					'options'  => $attrs['options'] ?? [],
					'id'       => $fieldId,
				],
				$extra
			)
		);

		$savedVal = $this->savedValues[ $fieldId ] ?? null;

		return $this->fieldRenderer->render( $field, $savedVal, $this->isCompleted );
	}

	private function registerMediaNodes(): void {
		foreach ( [ 'image', 'video', 'audio' ] as $mediaType ) {
			$this->node(
				$mediaType,
				function ( $_n, array $a ) use ( $mediaType ): string {
					$field = ActivityField::fromArray(
						[
							'type'  => $mediaType,
							'label' => '',
							'url'   => $a['url'] ?? '',
							'title' => $a['title'] ?? '',
						]
					);
					return $this->fieldRenderer->render( $field, null, false );
				}
			);
		}
	}
}
