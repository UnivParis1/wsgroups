(function ($) {
  var attrs = "uid,mail,displayName,cn,employeeType,departmentNumber,eduPersonPrimaryAffiliation,supannEntiteAffectation,supannRoleGenerique,supannEtablissement";
  var affiliation2order = { staff: 1, teacher: 2, researcher: 3, emeritus: 4, student: 5, affiliate: 6, member: 7 };
  var affiliation2text = { teacher: "Enseignants", student: "Etudiants", staff: "Biatss", researcher: "Chercheurs", emeritus: "Professeurs &eacute;m&eacute;rites", affiliate: "Invit&eacute;", member: "Divers", "": "Divers" };

  var getDetails = function (item) {
      var details = [];

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

      if (details.length) 
	  return "<div class='details'>" + details.join("<br>") + "</div>"
      else
	  return "";
  };

  var highlightMatched = function (text, searchedTokenL) {
	var textL = text.toLowerCase();
	var pos = textL.search(searchedTokenL);
	if (pos < 0) 
	    return textL;
	else {
	    var endPos = pos + searchedTokenL.length;
	    return text.substring(0, pos) + 
		"<span class='match'>" + text.substring(pos, endPos) + "</span>" +
		text.substring(endPos);
	}
  };

  var getNiceDisplayName = function (item) {
      var uid = item.uid;
      var displayName = item.displayName;
      var searchedTokenL = item.searchedTokenL;
      var display_uid = item.duplicateDisplayName;
      if (uid === searchedTokenL) {
	  display_uid = true;
	  uid = "<span class='match'>" + uid + "</span>";
      } else if (item.cn.toLowerCase().indexOf(searchedTokenL) == 0)
	  displayName = highlightMatched(item.cn, searchedTokenL);
      else
	  displayName = highlightMatched(displayName, searchedTokenL);

      if (display_uid)
	  displayName += " (" + uid + ")";

      return displayName;
  };

  var myRenderItem = function(ul, item) {
	if (item.pre)
	    $("<li class='kind'><span>" + item.pre + "</span></li>").appendTo(ul);

	var content = getNiceDisplayName(item) + getDetails(item);
	$("<li></li>").addClass(item.odd_even ? "odd" : "even")
	    .data("item.autocomplete", item)
	    .append("<a>" + content + "</a>")
	    .appendTo(ul);

      if (item.nbListeRouge)
	  $("<li></li>").addClass("warning").append(
	      item.nbListeRouge > 1 ?
		  "NB : des r&eacute;sultats ont &eacute;t&eacute; cach&eacute;s<br>&agrave; la demande des personnes." :
		  "NB : un r&eacute;sultat a &eacute;t&eacute; cach&eacute;<br>&agrave; la demande de la personne."
	  ).appendTo(ul);

      if (item.partialResults)
	  $("<li></li>").addClass("warning").append("La recherche est limit&eacute;e &agrave; " + item.partialResults + " r&eacute;sultats.<br>Pour les autres r&eacute;sultats, veuillez affiner la recherche.").appendTo(ul);
      if (item.partialResultsNoFullSearch)
	  $("<li></li>").addClass("warning").append("La recherche est limit&eacute;e.<br>Pour les autres r&eacute;sultats, veuillez affiner la recherche.").appendTo(ul);

  };

  var countOccurences = function (list) {
	var r = {};
	$.each(list, function (i, e) {
	    r[e] = (r[e] || 0) + 1;
	});
	return r;
  };

  var sortByAffiliation = function (items) {
      return items.sort(function(a,b) { 
	  return (affiliation2order[a.eduPersonPrimaryAffiliation] || 99) - (affiliation2order[b.eduPersonPrimaryAffiliation] || 99);
      });
  }

  var transformItems = function (items, wantedAttr, searchedToken) {
      var searchedTokenL = searchedToken.toLowerCase();
      var affiliation;
      var odd_even;
      // nb: "cn" is easer to compare since there is no accents. Two "displayName"s could be equal after removing accents.
      var cnOccurences = countOccurences($.map(items, function (item) { return item.cn }));
      var displayNameOccurences = countOccurences($.map(items, function (item) { return item.displayName }));
      $.each(items, function ( i, item ) {
	    item.label = item.displayName;
	    item.value = item[wantedAttr];
	    item.searchedTokenL = searchedTokenL;

	    if (affiliation != item.eduPersonPrimaryAffiliation) {
		affiliation = item.eduPersonPrimaryAffiliation;
		item.pre = affiliation2text[affiliation || ""];
	    }

	    if (displayNameOccurences[item.displayName] > 1 || cnOccurences[item.cn] > 1)
		item.duplicateDisplayName = true;

	    item.odd_even = odd_even = !odd_even;
	});
  };

  $.fn.autocompleteUser = function (searchUserURL, options) {
      if (!searchUserURL) throw "missing param searchUserURL";

      var settings = $.extend( 
	  { 'minLength' : 2,
	    'minLengthFullSearch' : 4,
	    'maxRows' : 10,
	    'wantedAttr' : 'uid',
	    'attrs' : attrs
	  }, options);

      var wsParams = $.extend({ 
	  maxRows: settings.maxRows, 
	  attrs: settings.attrs
      }, settings.wsParams);

      var source = function( request, response ) {
	  wsParams.token = request.term;
	    $.ajax({
		url: searchUserURL,
		dataType: "jsonp",
		crossDomain: true, // needed if searchUserURL is CAS-ified or on a different host than application using autocompleteUser
		data: wsParams,
		error: function () {
		    // we should display on error. but we do not have a nice error to display
		    // the least we can do is to show the user the request is finished!
		    response([]);
		},
		success: function (dataAll) {
		    data = $.grep(dataAll, function (item, i) { 
			return item.displayName !== "supannListeRouge"; 
		    });
		    nbListeRouge = dataAll.length - data.length;

		    data = sortByAffiliation(data);
		    transformItems(data, settings.wantedAttr, request.term);
		    if (data.length > 0) {
			if (data.length >= settings.maxRows) {
			    data[data.length-1].partialResults = settings.maxRows;
			} else if (request.term.length < settings.minLengthFullSearch) {
			    data[data.length-1].partialResultsNoFullSearch = 1;
			}
			data[data.length-1].nbListeRouge = nbListeRouge;
		    }
		    response(data);
		}
	    });
      };

      var params = {
	  minLength: settings.minLength,
	  source: source,
	  open: function () {
	    $('html,body').scrollTop($(this).offset().top);
	  }
      };

      if (settings.select) {
	  params.select = settings.select;
	  params.focus = function () {
	    // prevent update of <input>
	    return false;
	  };
      }
      this.autocomplete(params);

      this.data("autocomplete")._renderItem = myRenderItem;

      // below is useful when going back on the search values
      this.click(function () {
      	  $(this).autocomplete("search");
      });
  };



  $.fn.handlePlaceholderOnIE = function () {

      var handlePlaceholder = 'placeholder' in document.createElement('input');
      if (handlePlaceholder) return; // cool, the browser handle it, nothing to do

      this.each(function(){
	  var o = $(this);
	  if (o.attr("placeholder") =="") return;

          var prevColor;
          var displayPlaceholder = function(){
	      if(o.val()!="") return;
              o.val(o.attr("placeholder"));
              prevColor = o.css("color");
              o.css("color", "#808080");
	  };
	  o.focus(function(){
              o.css("color", prevColor);
	      if(o.val()==o.attr("placeholder")) o.val("");
	  });
	  o.blur(displayPlaceholder);
          displayPlaceholder();
      });

  };

})(jQuery);
