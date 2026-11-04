/**
 * JAT Search Intel — settings page helpers.
 */
// AI crawler access presets (GEO settings).
document.addEventListener( 'click', function ( e ) {
    var preset = e.target && e.target.getAttribute ? e.target.getAttribute( 'data-jsi-ai-preset' ) : null;
    if ( ! preset ) {
        return;
    }
    document.querySelectorAll( 'select[data-jsi-purpose]' ).forEach( function ( el ) {
        var purpose = el.getAttribute( 'data-jsi-purpose' );
        var block   = 'block' === preset || ( 'training' === preset && ( 'training' === purpose || 'signal' === purpose ) );
        el.value    = block ? 'block' : 'allow';
    } );
} );
