document.addEventListener( 'DOMContentLoaded', ( event ) => {
	document.querySelectorAll( '.test-form' ).forEach( ( e ) => {
		e.addEventListener( 'submit', ( event2 ) => {
			event2.preventDefault();

			fetch( 'api/issues/apitest', {
				'method': event2.target.getAttribute( 'method' ).toUpperCase(),
				'body': new URLSearchParams( new FormData( event2.target ) ),
			})
			.then( ( response ) => {
				if ( response['ok'] ) {
					return response.json();
				} else {
					throw 'Error';
				}
			})
			.then( ( data ) => {
				if ( data['error'] === undefined ) {
					event2.target.reset();
				}

				document.querySelector( '#result-json' ).textContent = JSON.stringify( data );
			})
			.catch( ( error ) => {
				console.log( error );
			} );
		} );
	} );

	if ( document.querySelector( '#issue-display' ) !== null ) {
		var project = window.location.pathname.match( /(?:\/.*)*\/(.*)\/$/ )[1];
		var path_prefix = window.location.pathname.replace( project + '/', '' );
		var url = path_prefix + 'api/issues/' + project;

		document.querySelector('#project-title').textContent = 'All issues for: ' + project;

		load_issues();

		document.querySelector( '#new-issue' ).addEventListener( 'submit', ( event2 ) => {
			event2.preventDefault();

			event2.target.setAttribute( 'action', path_prefix + 'api/issues/' + project );

			fetch( url, {
				'method': 'POST',
				'body': new URLSearchParams( new FormData( event2.target ) ),
			})
			.then( ( response ) => {
				if ( response['ok'] ) {
					return response.json();
				} else {
					throw 'Error';
				}
			})
			.then( ( data ) => {
				if ( data['error'] === undefined ) {
					event2.target.reset();
				}

				load_issues();
			})
			.catch( ( error ) => {
				console.log( error );
			} );
		} );

		document.querySelector( '#issue-display' ).addEventListener( 'submit', ( event2 ) => {
			if ( event2.target.closest( '.issue-form' ) ) {
				event2.preventDefault();

				fetch( url, {
					'method': event2.target.closest( '.issue-form' ).getAttribute( 'method' ).toUpperCase(),
					'body': new URLSearchParams( new FormData( event2.target.closest( '.issue-form' ) ) ),
				})
				.then( ( response ) => {
					if ( response['ok'] ) {
						return response.json();
					} else {
						throw 'Error';
					}
				})
				.then( ( data ) => {
					alert( data['error'] !== undefined ? data['error'] : data['result'] );
					load_issues();
				})
				.catch( ( error ) => {
					console.log( error );
				} );
			}
		} );

		function load_issues() {
			fetch( url, {
				'method': 'GET'
			} )
			.then( ( response ) => {
				if ( response['ok'] ) {
					return response.json();
				} else {
					throw 'Error';
				}
			})
			.then( ( data ) => {
				let html = '';

				data.forEach( ( elem ) => {
					let openstatus;

					(elem['open']) ? openstatus = 'open': openstatus = 'closed';

					html += `
					<div class="col-8 mb-3">
						<div class="card issue ${openstatus}">
							<h5 class="card-header id">ID: ${elem['id']}</h5>
							<div class="card-body">
								<h5 class="card-title">${elem['issue_title']} (${openstatus})</h5>
								<p class="card-text">${elem['issue_text']}</p>
								<p class="card-text">${elem['status_text']}</p>
							</div>
							<div class="card-footer text-muted">
								<div class="row">
									<div class="col">
										<span class="fw-semibold">Created by:</span> ${elem['created_by']}
									</div>
									<div class="col">
										<span class="fw-semibold">Assigned to:</span> ${elem['assigned_to']}
									</div>
								</div>
								<div class="row mb-2">
									<div class="col">
										<span class="fw-semibold">Created on:</span> ${elem['created_on']}
									</div>
									<div class="col">
										<span class="fw-semibold">Last updated:</span> ${elem['updated_on']}
									</div>
								</div>
								<div class="row">
									<div class="col">
										<form class="issue-form" method="put">
											<input type="hidden" name="id" value="${elem['id']}">
											<input type="hidden" name="open" value="false">
											<input type="submit" class="btn btn-dark" value="Close Issue">
										</form>
									</div>
									<div class="col d-flex justify-content-end">
										<form class="issue-form" method="delete">
											<input type="hidden" name="id" value="${elem['id']}">
											<input type="submit" class="btn btn-danger" value="Delete Issue">
										</form>
									</div>
								</div>
							</div>
						</div>
					</div>
					`;
				} );

				if ( html == '' ) {
					html = '<p class="text-center">No issues yet.</p>';
				}

				document.querySelector( '#issue-display' ).innerHTML = html;
			})
			.catch( ( error ) => {
				console.log( error );
			} );
		}
	}
} );