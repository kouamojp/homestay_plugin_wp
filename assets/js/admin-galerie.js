/* HomyStay Core : champ « Galerie photos » dans l'édition d'un bien. */
( function ( $ ) {
	function sync( $box ) {
		var ids = $box.find( '.hs-galerie-admin__liste li' ).map( function () {
			return $( this ).data( 'id' );
		} ).get();
		$( '#' + $box.data( 'target' ) ).val( ids.join( ',' ) );
	}

	$( function () {
		$( '.hs-galerie-admin' ).each( function () {
			var $box = $( this );
			var $liste = $box.find( '.hs-galerie-admin__liste' );
			var frame;

			$liste.sortable( { update: function () { sync( $box ); } } );

			$box.on( 'click', '.hs-galerie-admin__retirer', function ( e ) {
				e.preventDefault();
				$( this ).closest( 'li' ).remove();
				sync( $box );
			} );

			$box.on( 'click', '.hs-galerie-admin__ajouter', function ( e ) {
				e.preventDefault();
				if ( ! frame ) {
					frame = wp.media( {
						title: 'Photos du bien',
						button: { text: 'Ajouter à la galerie' },
						library: { type: 'image' },
						multiple: 'add'
					} );
					frame.on( 'select', function () {
						frame.state().get( 'selection' ).each( function ( att ) {
							var a = att.toJSON();
							if ( $liste.find( 'li[data-id="' + a.id + '"]' ).length ) {
								return;
							}
							var src = a.sizes && a.sizes.thumbnail ? a.sizes.thumbnail.url : a.url;
							$( '<li>' ).attr( 'data-id', a.id ).data( 'id', a.id )
								.append( $( '<img>' ).attr( { src: src, alt: '' } ) )
								.append( '<button type="button" class="hs-galerie-admin__retirer" aria-label="Retirer">×</button>' )
								.appendTo( $liste );
						} );
						sync( $box );
					} );
				}
				frame.open();
			} );
		} );
	} );
} )( jQuery );
