<?php
/**
 * Small HTML → Markdown converter for the AI-agent Markdown endpoints.
 *
 * Covers what post content actually contains: headings, paragraphs, lists,
 * links, images, emphasis, code, blockquotes, tables and line breaks.
 * Scripts, styles, forms and embeds are dropped.
 *
 * @package JAT_Search_Intel
 */

namespace JAT_Search_Intel;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PHP DOM API property names.

/**
 * HTML to Markdown.
 */
class Markdown {

	private const SKIP_TAGS = [ 'script', 'style', 'noscript', 'iframe', 'form', 'button', 'svg', 'template', 'nav' ];

	/**
	 * Convert an HTML fragment to Markdown.
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	public static function from_html( string $html ): string {
		$html = trim( $html );
		if ( '' === $html ) {
			return '';
		}

		if ( ! class_exists( '\DOMDocument' ) ) {
			return trim( wp_strip_all_tags( $html ) );
		}

		$doc = new \DOMDocument();
		$old = libxml_use_internal_errors( true );
		$doc->loadHTML( '<?xml encoding="UTF-8"><div id="jsi-root">' . $html . '</div>', LIBXML_NOERROR | LIBXML_NOWARNING );
		libxml_clear_errors();
		libxml_use_internal_errors( $old );

		$root = $doc->getElementById( 'jsi-root' );
		if ( ! $root ) {
			return trim( wp_strip_all_tags( $html ) );
		}

		$md = self::children( $root );

		// Tidy whitespace: at most one blank line between blocks.
		$md = preg_replace( "/[ \t]+\n/", "\n", $md );
		$md = preg_replace( "/\n{3,}/", "\n\n", (string) $md );

		return trim( (string) $md ) . "\n";
	}

	/**
	 * Render all child nodes.
	 *
	 * @param \DOMNode $node  Parent.
	 * @param int      $depth List depth.
	 * @return string
	 */
	private static function children( \DOMNode $node, int $depth = 0 ): string {
		$out = '';
		foreach ( $node->childNodes as $child ) {
			$out .= self::node( $child, $depth );
		}
		return $out;
	}

	/**
	 * Render one node.
	 *
	 * @param \DOMNode $node  Node.
	 * @param int      $depth List depth.
	 * @return string
	 */
	private static function node( \DOMNode $node, int $depth ): string {
		if ( XML_TEXT_NODE === $node->nodeType ) {
			return preg_replace( '/\s+/', ' ', $node->textContent );
		}
		if ( ! $node instanceof \DOMElement ) {
			return '';
		}

		$tag = strtolower( $node->tagName );
		if ( in_array( $tag, self::SKIP_TAGS, true ) ) {
			return '';
		}

		switch ( $tag ) {
			case 'h1':
			case 'h2':
			case 'h3':
			case 'h4':
			case 'h5':
			case 'h6':
				$text = trim( self::inline( $node ) );
				return '' === $text ? '' : "\n\n" . str_repeat( '#', (int) $tag[1] ) . ' ' . $text . "\n\n";
			case 'p':
				$text = trim( self::children( $node, $depth ) );
				return '' === $text ? '' : "\n\n" . $text . "\n\n";
			case 'br':
				return "  \n";
			case 'hr':
				return "\n\n---\n\n";
			case 'strong':
			case 'b':
				$text = trim( self::children( $node, $depth ) );
				return '' === $text ? '' : '**' . $text . '**';
			case 'em':
			case 'i':
				$text = trim( self::children( $node, $depth ) );
				return '' === $text ? '' : '*' . $text . '*';
			case 'code':
				if ( $node->parentNode instanceof \DOMElement && 'pre' === strtolower( $node->parentNode->tagName ) ) {
					return $node->textContent;
				}
				return '`' . str_replace( '`', '\`', $node->textContent ) . '`';
			case 'pre':
				return "\n\n```\n" . rtrim( $node->textContent ) . "\n```\n\n";
			case 'blockquote':
				$inner = trim( self::children( $node, $depth ) );
				return "\n\n" . preg_replace( '/^/m', '> ', $inner ) . "\n\n";
			case 'a':
				$text = trim( self::children( $node, $depth ) );
				$href = $node->getAttribute( 'href' );
				if ( '' === $href || 0 === strpos( $href, 'javascript:' ) ) {
					return $text;
				}
				return '[' . ( '' === $text ? $href : $text ) . '](' . $href . ')';
			case 'img':
				$src = $node->getAttribute( 'src' );
				return '' === $src ? '' : '![' . $node->getAttribute( 'alt' ) . '](' . $src . ')';
			case 'ul':
			case 'ol':
				return "\n\n" . self::list_items( $node, 'ol' === $tag, $depth ) . "\n";
			case 'table':
				return "\n\n" . self::table( $node ) . "\n\n";
			case 'figure':
				$class = ' ' . $node->getAttribute( 'class' ) . ' ';
				if ( false !== strpos( $class, 'wp-block-embed' ) ) {
					$url = '';
					foreach ( $node->getElementsByTagName( 'iframe' ) as $iframe ) {
						$url = $iframe->getAttribute( 'src' );
						break;
					}
					if ( '' === $url && preg_match( '~https?://\S+~', $node->textContent, $m ) ) {
						$url = $m[0];
					}
					$caption = '';
					foreach ( $node->getElementsByTagName( 'figcaption' ) as $fc ) {
						$caption = trim( $fc->textContent );
					}
					return '' === $url ? '' : "\n\n[" . ( '' !== $caption ? $caption : 'Embedded media' ) . '](' . $url . ")\n\n";
				}
				return "\n\n" . trim( self::children( $node, $depth ) ) . "\n\n";
			case 'div':
			case 'section':
			case 'article':
			case 'header':
			case 'footer':
			case 'main':
			case 'aside':
			case 'details':
			case 'summary':
			case 'dl':
			case 'dt':
			case 'dd':
				$inner = trim( self::children( $node, $depth ) );
				return '' === $inner ? '' : "\n\n" . $inner . "\n\n";
			case 'figcaption':
				$text = trim( self::children( $node, $depth ) );
				return '' === $text ? '' : "\n*" . $text . "*\n";
			default:
				return self::children( $node, $depth );
		}
	}

	/**
	 * Inline text of an element.
	 *
	 * @param \DOMNode $node Node.
	 * @return string
	 */
	private static function inline( \DOMNode $node ): string {
		return preg_replace( '/\s+/', ' ', self::children( $node ) );
	}

	/**
	 * Render list items.
	 *
	 * @param \DOMElement $list    List element.
	 * @param bool        $ordered Ordered list.
	 * @param int         $depth   Nesting depth.
	 * @return string
	 */
	private static function list_items( \DOMElement $list, bool $ordered, int $depth ): string {
		$out    = '';
		$number = 1;
		$indent = str_repeat( '  ', $depth );

		foreach ( $list->childNodes as $item ) {
			if ( ! $item instanceof \DOMElement || 'li' !== strtolower( $item->tagName ) ) {
				continue;
			}
			$text   = '';
			$nested = '';
			foreach ( $item->childNodes as $child ) {
				if ( $child instanceof \DOMElement && in_array( strtolower( $child->tagName ), [ 'ul', 'ol' ], true ) ) {
					$nested .= self::list_items( $child, 'ol' === strtolower( $child->tagName ), $depth + 1 );
				} else {
					$text .= self::node( $child, $depth + 1 );
				}
			}
			$bullet = $ordered ? ( $number++ ) . '.' : '-';
			$out   .= $indent . $bullet . ' ' . trim( preg_replace( '/\s+/', ' ', $text ) ) . "\n" . $nested;
		}

		return $out;
	}

	/**
	 * Render a table as a pipe table.
	 *
	 * @param \DOMElement $table Table element.
	 * @return string
	 */
	private static function table( \DOMElement $table ): string {
		$rows = [];
		foreach ( $table->getElementsByTagName( 'tr' ) as $tr ) {
			$cells = [];
			foreach ( $tr->childNodes as $cell ) {
				if ( $cell instanceof \DOMElement && in_array( strtolower( $cell->tagName ), [ 'td', 'th' ], true ) ) {
					$cells[] = str_replace( '|', '\|', trim( self::inline( $cell ) ) );
				}
			}
			if ( $cells ) {
				$rows[] = $cells;
			}
		}
		if ( ! $rows ) {
			return '';
		}

		$width = max( array_map( 'count', $rows ) );
		$lines = [];
		foreach ( $rows as $i => $cells ) {
			$cells   = array_pad( $cells, $width, '' );
			$lines[] = '| ' . implode( ' | ', $cells ) . ' |';
			if ( 0 === $i ) {
				$lines[] = '|' . str_repeat( ' --- |', $width );
			}
		}
		return implode( "\n", $lines );
	}
}
