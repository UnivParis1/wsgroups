$(function() {
    $( "#foo" ).autocomplete({
	source: function( request, response ) {
	    $.ajax({
		url: "http://ticetest.univ-paris1.fr/wsgroups/search",
		dataType: "jsonp",
		data: { maxRows: 10, token: request.term },
		success: function( data ) {
		    response( $.merge(
			$.map( data.users, function( item ) {
			    return { label: item.displayName, value: item.uid }
			}),
			$.map( data.groups, function( item ) {
			    return { label: item.name, value: item.key }
			})
		    ));
		}
	    });
	},
	minLength: 4,
	select: function( event, ui ) { },
	open: function() { },
	close: function() { }
    });
});
