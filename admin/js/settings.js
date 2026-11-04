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
    var signals = {
        allow: { 'search': 'yes', 'ai-input': 'yes', 'ai-train': 'yes' },
        training: { 'search': 'yes', 'ai-input': 'yes', 'ai-train': 'no' },
        block: { 'search': 'yes', 'ai-input': 'no', 'ai-train': 'no' }
    }[ preset ];
    document.querySelectorAll( 'select[data-jsi-signal]' ).forEach( function ( el ) {
        if ( signals ) {
            el.value = signals[ el.getAttribute( 'data-jsi-signal' ) ];
        }
    } );
} );
