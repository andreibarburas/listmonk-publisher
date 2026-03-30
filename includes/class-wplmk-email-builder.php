<?php
defined( 'ABSPATH' ) || exit;

/**
 * Builds the HTML email body for a post.
 * Shared by WPLMK_Campaign (live sends) and WPLMK_Settings (test sends).
 */
class WPLMK_Email_Builder {

    /**
     * Build and return the full HTML body for the given post.
     */
    public static function build( WP_Post $post ): string {
        $font_stack = "'Lora', Georgia, 'Times New Roman', serif";
        $title      = get_the_title( $post );
        $url        = get_permalink( $post );
        $parts      = [];

        // Inbox preview text (hidden preheader).
        $excerpt = get_the_excerpt( $post );
        if ( $excerpt ) {
            $parts[] = sprintf(
                '<div style="display:none;max-height:0;overflow:hidden;mso-hide:all;">%s&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;</div>',
                esc_html( wp_trim_words( $excerpt, 20, '' ) )
            );
        }

        // Google Fonts import (honoured by Apple Mail and some desktop clients).
        $parts[] = '<div style="display:none;max-height:0;overflow:hidden;">'
            . "<style>@import url('https://fonts.googleapis.com/css2?family=Lora:ital,wght@0,400;0,600;1,400&display=swap');</style>"
            . '</div>';

        // Featured image.
        $thumbnail_id = get_post_thumbnail_id( $post->ID );
        if ( $thumbnail_id ) {
            $img_src = wp_get_attachment_image_url( $thumbnail_id, 'large' );
            if ( $img_src ) {
                $parts[] = sprintf(
                    '<div style="margin-bottom:24px;"><img src="%s" alt="%s" width="600" style="width:100%%;max-width:600px;height:auto;display:block;border:0;" /></div>',
                    esc_url( $img_src ),
                    esc_attr( $title )
                );
            }
        }

        // Title.
        $parts[] = sprintf(
            '<h2 style="margin:0 0 16px;font-family:%s;font-size:24px;font-weight:600;line-height:1.3;color:#e8e8e8;">%s</h2>',
            $font_stack,
            esc_html( $title )
        );

        // Opening paragraphs (up to ~500 chars of plain text).
        $content = apply_filters( 'the_content', $post->post_content );
        preg_match_all( '/<p[\s>].*?<\/p>/is', $content, $matches );
        $paragraphs  = $matches[0] ?? [];
        $accumulated = '';
        $char_count  = 0;

        foreach ( $paragraphs as $p ) {
            $plain = strip_tags( $p );
            if ( $char_count > 0 && ( $char_count + strlen( $plain ) ) > 500 ) {
                break;
            }
            $accumulated .= $p . "\n";
            $char_count  += strlen( $plain );
            if ( $char_count >= 500 ) {
                break;
            }
        }

        if ( $accumulated ) {
            $parts[] = sprintf(
                '<div style="font-family:%s;font-size:16px;line-height:1.7;color:#c8c8c8;">%s</div>',
                $font_stack,
                $accumulated
            );
        }

        // Read more button.
        $parts[] = sprintf(
            '<div style="margin-top:32px;"><a href="%s" style="display:inline-block;padding:12px 28px;background-color:transparent;color:#e8e8e8;text-decoration:none;font-family:%s;font-size:13px;font-weight:600;letter-spacing:0.08em;text-transform:uppercase;border:1px solid #555555;">Read the full article &rarr;</a></div>',
            esc_url( $url ),
            $font_stack
        );

        return implode( "\n", $parts );
    }
}
