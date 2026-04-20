/**
 * dc.js
 * Display Content JS for WXC/WHx4.
 *
 * Handles:
 *   - Sticky navigation on scroll
 *   - zoom-fade entrance animation applied to images as they enter the viewport
 */

jQuery( document ).ready( function ( $ ) {

    // -------------------------------------------------------------------------
    // Sticky navigation
    // -------------------------------------------------------------------------

    var $nav    = $( '#site-navigation' );
    var navTop  = $nav.length ? $nav.offset().top : 0;

    // Bail early if there is no nav element on this page
    if ( $nav.length ) {
        $( window ).on( 'scroll.stickyNav', function () {
            $nav.toggleClass( 'sticky', $( window ).scrollTop() > navTop );
        } );
    }


    // -------------------------------------------------------------------------
    // Zoom-fade entrance animation
    //
    // Images start at opacity:0 (set in CSS). When an image enters the viewport,
    // we add .zoom-fade which triggers the CSS animation and brings it to opacity:1.
    //
    // Images inside .wxc-card__image.hoverZoom are excluded because the hoverZoom
    // class already handles their entrance animation via CSS.
    // -------------------------------------------------------------------------

    /**
     * Returns true if the element's bounding rect intersects the viewport.
     *
     * @param  {Element} el
     * @return {boolean}
     */
    function isVisible( el ) {
        var rect       = el.getBoundingClientRect();
        var viewHeight = Math.max( document.documentElement.clientHeight, window.innerHeight );
        return rect.bottom > 0 && rect.top < viewHeight;
    }

    /**
     * Apply zoom-fade to images that are visible and haven't been animated yet.
     * Skips images that are already animated, are print-only, or are inside
     * a hoverZoom container (those have their own CSS entrance animation).
     */
    function applyZoomFade() {
        $( 'img' ).each( function () {
            var $img = $( this );

            // Skip if already animated
            if ( $img.hasClass( 'zoom-fade' ) ) {
                return;
            }

            // Skip print-only images
            if ( $img.hasClass( 'print-only' ) ) {
                return;
            }

            // Skip images inside hoverZoom containers (.wxc-card__image.hoverZoom)
            // — those have their own CSS entrance animation
            if ( $img.closest( '.wxc-card__image.hoverZoom' ).length ) {
                return;
            }

            if ( isVisible( this ) ) {
                $img.addClass( 'zoom-fade' );
            }
        } );

        // Captions use a slower variant
        $( '.featured_image_caption, .wp-caption-text' ).each( function () {
            if ( isVisible( this ) && ! $( this ).hasClass( 'zoom-fade-slow' ) ) {
                $( this ).addClass( 'zoom-fade-slow' );
            }
        } );
    }

    // Run on load and on scroll
    applyZoomFade();
    $( window ).on( 'scroll.zoomFade', applyZoomFade );

    // Re-run after EM async search results load
    // TODO: remove once Events Manager is fully retired in favour of WHx4 Events module
    $( document ).on( 'em_search_loaded', applyZoomFade );

} );