<?php

declare(strict_types=1);

namespace AllCoach\Frontend;

defined( 'ABSPATH' ) || exit;

use AllCoach\Domain\Activity\ActivityField;

class ActivityFieldRenderer {

	public function render( ActivityField $field, mixed $savedVal, bool $isCompleted ): string {
		return match ( $field->getType() ) {
			'short_text'      => $this->renderText( $field, $savedVal, $isCompleted ),
			'long_text'       => $this->renderTextarea( $field, $savedVal, $isCompleted ),
			'multiple_choice' => $this->renderRadio( $field, $savedVal, $isCompleted ),
			'checkbox'        => $this->renderCheckbox( $field, $savedVal, $isCompleted ),
			'date'            => $this->renderDate( $field, $savedVal, $isCompleted ),
			'file'            => $this->renderFile( $field, $savedVal, $isCompleted ),
			'image'           => $this->renderImage( $field ),
			'video'           => $this->renderVideo( $field ),
			'audio'           => $this->renderAudio( $field ),
			default           => '',
		};
	}

	// private function renderContent( ActivityField $field ): string {
	//  return '<div class="ac-act-block">' . wp_kses_post( $field->getContent() ?? '' ) . '</div>';
	// }

	private function renderText( ActivityField $field, mixed $savedVal, bool $isCompleted ): string {
		$attrs          = $this->baseAttrs( $field, $isCompleted );
		$attrs['type']  = 'text';
		$attrs['class'] = 'ac-act-field';
		if ( $savedVal !== null ) {
			$attrs['value'] = (string) $savedVal;
		}

		return $this->block(
			$field,
			'<input ' . $this->attrs( $attrs ) . '>'
		);
	}

	private function renderTextarea( ActivityField $field, mixed $savedVal, bool $isCompleted ): string {
		$attrs          = $this->baseAttrs( $field, $isCompleted );
		$attrs['class'] = 'ac-act-field';
		$attrs['rows']  = '3';
		$value          = $savedVal !== null ? esc_textarea( (string) $savedVal ) : '';

		return $this->block(
			$field,
			'<textarea ' . $this->attrs( $attrs ) . '>' . $value . '</textarea>'
		);
	}

	private function renderRadio( ActivityField $field, mixed $savedVal, bool $isCompleted ): string {
		$html = '';
		foreach ( $field->getOptions() as $option ) {
			$attrs = [
				'type'       => 'radio',
				'name'       => $field->getId() ?? '',
				'data-field' => $field->getId() ?? '',
				'value'      => $option['id'],
			];
			if ( $savedVal === $option['id'] ) {
				$attrs['checked'] = true;
			}
			if ( $isCompleted ) {
				$attrs['disabled'] = true;
			}
			if ( $field->isRequired() ) {
				$attrs['required'] = true;
			}
			$html .= '<label class="ac-act-option"><input ' . $this->attrs( $attrs ) . '>'
				. esc_html( $option['label'] ) . '</label>';
		}

		return $this->block( $field, $html );
	}

	private function renderCheckbox( ActivityField $field, mixed $savedVal, bool $isCompleted ): string {
		$checkedIds = is_array( $savedVal ) ? $savedVal : [];
		$html       = '';
		$isFirst    = true;
		foreach ( $field->getOptions() as $option ) {
			$attrs   = [
				'type'       => 'checkbox',
				'name'       => ( $field->getId() ?? '' ) . '[]',
				'data-field' => $field->getId() ?? '',
				'value'      => $option['id'],
				'required'   => $isFirst && $field->isRequired(),
			];
			$isFirst = false;
			if ( in_array( $option['id'], $checkedIds, true ) ) {
				$attrs['checked'] = true;
			}
			if ( $isCompleted ) {
				$attrs['disabled'] = true;
			}
			$html .= '<label class="ac-act-option"><input ' . $this->attrs( $attrs ) . '>'
				. esc_html( $option['label'] ) . '</label>';
		}

		return $this->block( $field, $html );
	}

	private function renderDate( ActivityField $field, mixed $savedVal, bool $isCompleted ): string {
		$attrs          = $this->baseAttrs( $field, $isCompleted );
		$attrs['type']  = 'date';
		$attrs['class'] = 'ac-act-field';
		if ( $savedVal !== null ) {
			$attrs['value'] = (string) $savedVal;
		}

		return $this->block(
			$field,
			'<input ' . $this->attrs( $attrs ) . '>'
		);
	}

	private function renderFile( ActivityField $field, mixed $savedVal, bool $isCompleted ): string {
		$fieldId = $field->getId() ?? '';
		$accept  = $field->getExtra()['accept'] ?? null;

		if ( $isCompleted ) {
			if ( $savedVal ) {
				if ( is_array( $savedVal ) ) {
					$url  = (string) ( $savedVal['url'] ?? '' );
					$name = (string) ( $savedVal['name'] ?? basename( $url ) );
				} else {
					$url  = (string) $savedVal;
					$name = basename( $url );
				}
				if ( $url ) {
					$input = '<a href="' . esc_url( $url ) . '" class="ac-act-file-link" target="_blank" rel="noopener">'
						. esc_html( $name ) . '</a>';
				} else {
					$input = '<span class="ac-act-file-empty">' . esc_html__( 'No file uploaded.', 'allcoach' ) . '</span>';
				}
			} else {
				$input = '<span class="ac-act-file-empty">' . esc_html__( 'No file uploaded.', 'allcoach' ) . '</span>';
			}
		} else {
			$attrs = [
				'type'       => 'file',
				'name'       => $fieldId,
				'data-field' => $fieldId,
				'class'      => 'ac-act-file-input',
			];
			if ( $accept ) {
				$attrs['accept'] = $accept;
			}
			if ( $field->isRequired() ) {
				$attrs['required'] = true;
			}
			$input = '<input ' . $this->attrs( $attrs ) . '>';
		}

		return $this->block( $field, $input );
	}

	private function renderImage( ActivityField $field ): string {
		$extra = $field->getExtra();
		$url   = $extra['url'] ?? '';
		$title = $extra['title'] ?? '';

		if ( ! $url ) {
			return '';
		}

		return '<div class="ac-act-block">'
			. '<img src="' . esc_url( $url ) . '" alt="' . esc_attr( $title ) . '" class="ac-act-media-img">'
			. ( $title ? '<p class="ac-act-media-caption">' . esc_html( $title ) . '</p>' : '' )
			. '</div>';
	}

	private function renderVideo( ActivityField $field ): string {
		$extra = $field->getExtra();
		$url   = $extra['url'] ?? '';
		$title = $extra['title'] ?? '';

		if ( ! $url ) {
			return '';
		}

		return '<div class="ac-act-block">'
			. '<video controls class="ac-act-media-video">'
			. '<source src="' . esc_url( $url ) . '">'
			. '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Download video', 'allcoach' ) . '</a>'
			. '</video>'
			. ( $title ? '<p class="ac-act-media-caption">' . esc_html( $title ) . '</p>' : '' )
			. '</div>';
	}

	private function renderAudio( ActivityField $field ): string {
		$extra = $field->getExtra();
		$url   = $extra['url'] ?? '';
		$title = $extra['title'] ?? '';

		if ( ! $url ) {
			return '';
		}

		return '<div class="ac-act-block">'
			. '<audio controls class="ac-act-media-audio">'
			. '<source src="' . esc_url( $url ) . '">'
			. '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Download audio', 'allcoach' ) . '</a>'
			. '</audio>'
			. ( $title ? '<p class="ac-act-media-caption">' . esc_html( $title ) . '</p>' : '' )
			. '</div>';
	}

	private function baseAttrs( ActivityField $field, bool $isCompleted ): array {
		$attrs = [
			'name'       => $field->getId() ?? '',
			'data-field' => $field->getId() ?? '',
		];
		if ( $field->getPlaceholder() ) {
			$attrs['placeholder'] = $field->getPlaceholder();
		}
		if ( $isCompleted ) {
			$attrs['disabled'] = true;
		}
		if ( $field->isRequired() ) {
			$attrs['required'] = true;
		}

		return $attrs;
	}

	private function attrs( array $attrs ): string {
		$parts = [];
		foreach ( $attrs as $key => $value ) {
			if ( $value === true ) {
				$parts[] = esc_attr( $key );
			} elseif ( $value !== false && $value !== null ) {
				$parts[] = esc_attr( $key ) . '="' . esc_attr( (string) $value ) . '"';
			}
		}

		return implode( ' ', $parts );
	}

	private function block( ActivityField $field, string $inputHtml ): string {
		return '<div class="ac-act-block" data-field-id="' . esc_attr( $field->getId() ?? '' ) . '">'
			. '<div class="ac-act-block__label">' . esc_html( $field->getLabel() ) . '</div>'
			. $inputHtml
			. '</div>';
	}
}
