<?php

declare(strict_types=1);

namespace AllCoach\Domain\Activity;

defined( 'ABSPATH' ) || exit;

final class ActivityField {

	public function __construct(
		private readonly string $type,
		private readonly string|array $label,
		private readonly bool $required = false,
		private readonly ?string $placeholder = null,
		private readonly array $options = [],
		private readonly array $validation = [],
		private readonly array $extra = [],
		private readonly ?string $id = null,
		private readonly ?string $content = null,
	) {}

	public function getType(): string {
		return $this->type;
	}

	public function getId(): ?string {
		return $this->id;
	}

	public function getLabelRaw(): string|array {
		return $this->label;
	}

	public function getLabel( string $locale = '' ): string {
		if ( is_string( $this->label ) ) {
			return $this->label;
		}

		if ( $locale !== '' ) {
			if ( isset( $this->label[ $locale ] ) ) {
				return $this->label[ $locale ];
			}
			$lang = substr( $locale, 0, 2 );
			if ( isset( $this->label[ $lang ] ) ) {
				return $this->label[ $lang ];
			}
		}

		return $this->label['en'] ?? ( ! empty( $this->label ) ? array_values( $this->label )[0] : '' );
	}

	public function isRequired(): bool {
		return $this->required;
	}

	public function getPlaceholder(): ?string {
		return $this->placeholder;
	}

	public function getOptions(): array {
		return $this->options;
	}

	public function getValidation(): array {
		return $this->validation;
	}

	public function getExtra(): array {
		return $this->extra;
	}

	public function getContent(): ?string {
		return $this->content;
	}

	public function withLabel( string|array $label ): self {
		return new self(
			$this->type,
			$label,
			$this->required,
			$this->placeholder,
			$this->options,
			$this->validation,
			$this->extra,
			$this->id,
			$this->content,
		);
	}

	public function toArray(): array {
		if ( $this->type === 'content' ) {
			$data = [
				'type'    => $this->type,
				'content' => $this->content ?? '',
			];
			if ( $this->id !== null ) {
				$data['id'] = $this->id;
			}
			return array_merge( $data, $this->extra );
		}

		$data = [
			'type'     => $this->type,
			'label'    => $this->label,
			'required' => $this->required,
		];

		if ( $this->id !== null ) {
			$data['id'] = $this->id;
		}
		if ( $this->placeholder !== null ) {
			$data['placeholder'] = $this->placeholder;
		}
		if ( ! empty( $this->options ) ) {
			$data['options'] = $this->options;
		}
		if ( ! empty( $this->validation ) ) {
			$data['validation'] = $this->validation;
		}

		return array_merge( $data, $this->extra );
	}

	public static function fromArray( array $data ): self {
		$known = [ 'type', 'label', 'required', 'placeholder', 'options', 'validation', 'id', 'content' ];

		return new self(
			type:        $data['type'] ?? 'text',
			label:       $data['label'] ?? '',
			required:    (bool) ( $data['required'] ?? false ),
			placeholder: $data['placeholder'] ?? null,
			options:     $data['options'] ?? [],
			validation:  $data['validation'] ?? [],
			extra:       array_diff_key( $data, array_flip( $known ) ),
			id:          isset( $data['id'] ) ? (string) $data['id'] : null,
			content:     $data['content'] ?? null,
		);
	}
}
