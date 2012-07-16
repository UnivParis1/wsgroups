
function inspect(o) {
   s = ""
   for (prop in o) s += prop + "=>" + o[prop] + " "
   return s
}

$(function() {
    var highlightMatched = function (text, tokenL) {
	var textL = text.toLowerCase();
	var pos = textL.search(tokenL);
	if (pos < 0) 
	    return textL;
	else {
	    var endPos = pos + tokenL.length;
	    return text.substring(0, pos) + "<span class='match'>" + text.substring(pos, endPos) + "</span>" + text.substring(endPos);
	}
    };
    var countOccurences = function (list) {
	var r = {};
	$.each(list, function (i, e) {
	    r[e] = (r[e] || 0) + 1;
	});
	return r;
    }

    var affiliation2text = { teacher: "Enseignants", student: "Etudiants", staff: "Biatss", researcher: "Chercheurs", emeritus: "Professeurs &eacute;m&eacute;rites", affiliate: "Invit&eacute;", member: "Divers" };

    $( "#foo" ).autocomplete({
	source: function( request, response ) {
	    $.ajax({
		url: "http://ticetest.univ-paris1.fr/web-service-groups/searchUser",
		dataType: "jsonp",
		data: { maxRows: 10, token: request.term, attrs: "uid,mail,displayName,cn,employeeType,departmentNumber,eduPersonPrimaryAffiliation,supannEntiteAffectation,supannRoleGenerique,supannEtablissement" },
		open: function () {
		    //$('html, body').stop().animate({ scrollLeft: $("#foo").offset().left }, 1000);
		},
		success: function( data ) {
		    var affiliation;
		    var odd_even;
		    var displayNameOccurences = countOccurences(data.map(function (item) { return item.displayName }));
		    response(
			$.map(data.sort(function(a,b) { return a.eduPersonPrimaryAffiliation < b.eduPersonPrimaryAffiliation }),
			      function( item ) {
				  item.label = item.displayName;
				  item.value = item.uid;
				  item.token = request.term;
				  if (item.eduPersonPrimaryAffiliation && affiliation != item.eduPersonPrimaryAffiliation) {
				      affiliation = item.eduPersonPrimaryAffiliation;
				      item.pre = affiliation2text[affiliation];
				  }
				  if (displayNameOccurences[item.displayName] > 1)
				      item.duplicateDisplayName = true;
				  item.odd_even = odd_even = !odd_even;
				  return item;
			})
		    );
		}
	    });
	},
	minLength: 4
    }).data("autocomplete")._renderItem = function(ul, item) {
	var uid = item.uid;
	var displayName = item.displayName;
	var tokenL = item.token.toLowerCase()
	var display_uid = item.duplicateDisplayName;
	if (uid === tokenL) {
	    display_uid = true;
	    uid = "<span class='match'>" + uid + "</span>";
	} else if (item.cn.toLowerCase().indexOf(tokenL) == 0)
	    displayName = highlightMatched(item.cn, tokenL);
	else
	    displayName = highlightMatched(displayName, tokenL);

	var details = [];

	if (display_uid)
	    displayName += " (" + uid + ")";
	if (item.duplicateDisplayName) {
	    details.push(item.mail);
	}
	if (item.employeeType)
	    details.push(item.employeeType);
	if (item.supannRoleGenerique)
	    details.push(item.supannRoleGenerique);
	if (item.supannEntiteAffectation) {
	    var prev = details.pop();
	    details.push((prev ? prev + " - " : '') + item.supannEntiteAffectation.join(" - "));
	}
	if (item.departmentNumber) {
	    details.push((item.departmentNumber.count >= 2 ? "Disciplines : " : "Discipline : ") + item.departmentNumber.join(' - '));
	}
	if (item.supannEtablissement)
	    details.push(item.supannEtablissement);

	var content = displayName;
	if (details.length) content += "<div class='details'>" + details.join("<br>") + "</div>";
	if (item.pre)
	    $("<li class='kind strikethrough'><span>" + item.pre + "</span></li>").appendTo(ul);

	var li = $("<li></li>").addClass(item.odd_even ? "odd" : "even")
	    .data("item.autocomplete", item)
	    .append("<a>" + content + "</a>")
	    .appendTo(ul);

	//$('html,body').animate({scrollTop:$("#foo").offset().top}, 500);
	return li;
    };
});
