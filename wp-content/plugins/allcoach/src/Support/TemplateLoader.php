<?php

declare(strict_types=1);

namespace AllCoach\Support;

defined( 'ABSPATH' ) || exit;

use AllCoach\Core\Constants;
use AllCoach\Traits\Hooks;

class TemplateLoader {

	use Hooks;

	private string $pluginPath;
	private string $templatePath = 'allcoach';

	public function __construct() {
		$this->pluginPath = Constants::path( 'templates/' );
	}

	/**
	 * Locate a template file.
	 *
	 * Search order:
	 * 1. Child theme: child-theme/all-coach/{template-name}
	 * 2. Parent theme: theme/all-coach/{template-name}
	 * 3. Plugin: plugin/templates/{template-name}
	 */
	public function locateTemplate( string $templateName ): string {
		$template = locate_template(
			[
				trailingslashit( $this->templatePath ) . $templateName,
			]
		);

		if ( ! $template ) {
			$pluginTemplate = $this->pluginPath . $templateName;
			if ( file_exists( $pluginTemplate ) ) {
				$template = $pluginTemplate;
			}
		}

		return $this->applyFilters( 'allcoach:template:locate', $template, $templateName );
	}

	public function loadTemplate( string $templateName, array $args = [] ): void {
		$template = $this->locateTemplate( $templateName );

		if ( ! $template ) {
			return;
		}

		$args = $this->applyFilters( 'allcoach:template:args', $args, $templateName );

		$this->doAction( 'allcoach:template:before', $templateName, $template, $args );
		$this->doAction( "allcoach:template:before:{$templateName}", $template, $args );

		$this->includeTemplate( $template, $args );

		$this->doAction( 'allcoach:template:after', $templateName, $template, $args );
		$this->doAction( "allcoach:template:after:{$templateName}", $template, $args );
	}

	public function getTemplatePart( string $slug, string $name = '', array $args = [] ): void {
		$templates = [];
		if ( $name ) {
			$templates[] = "{$slug}-{$name}.php";
		}
		$templates[] = "{$slug}.php";
		$this->loadFirstTemplate( $templates, $args );
	}

	private function loadFirstTemplate( array $templates, array $args = [] ): void {
		foreach ( $templates as $templateName ) {
			$template = $this->locateTemplate( $templateName );

			if ( $template ) {
				$this->includeTemplate( $template, $args );
				return;
			}
		}
	}


	private function includeTemplate( string $template, array $args = [] ): void {
		$render = static function ( string $template, array $args ): void {
			foreach ( $args as $key => $value ) {
				$$key = $value;
			}
			include $template;
		};
		$render( $template, $args );
	}

	public function getTemplateContent( string $templateName, array $args = [] ): string {
		ob_start();
		$this->loadTemplate( $templateName, $args );
		return ob_get_clean();
	}

	public function templateExists( string $templateName ): bool {
		return ! empty( $this->locateTemplate( $templateName ) );
	}

	public function setTemplatePath( string $path ): void {
		$this->templatePath = $path;
	}

	public function getTemplatePath(): string {
		return $this->templatePath;
	}
}
