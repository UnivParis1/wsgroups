(function ($) {
  var attrs = "uid,mail,displayName,cn,employeeType,departmentNumber,eduPersonPrimaryAffiliation,supannEntiteAffectation-ou,supannRoleGenerique,supannEtablissement";
    var affiliation2order = { staff: 1, teacher: 2, researcher: 3, emeritus: 4, student: 5, affiliate: 6, alum: 7, member: 8, "registered-reader": 9, "library-walk-in": 10 };
    var affiliation2text = { teacher: "Enseignants", student: "Etudiants", staff: "Biatss", researcher: "Chercheurs", emeritus: "Professeurs &eacute;m&eacute;rites", affiliate: "Invit&eacute;", alum: "Anciens &eacute;tudiants", retired: "Retrait&eacute;s", "registered-reader": "Lecteur externe", "library-walk-in": "Visiteur biblioth&egrave;que" };

  var category2order = { structures: 5, affiliation: 5, diploma: 1, elp: 2, gpelp: 3, gpetp: 4 };

  var category2text = {
      structures: 'Directions / Composantes / Laboratoires',
      location: 'Sites',
      affiliation: 'Directions / Composantes / Laboratoires',
      diploma: 'Dipl&ocirc;mes / &Eacute;tapes',
      elp: 'Groupes Mati&egrave;res',
      gpelp: 'Groupes TD'
  };
  var subAndSuper_category2text = {
      structures: 'Groupes parents',
      affiliation: 'Groupes parents',
      diploma: '&Eacute;tapes associ&eacute;es',
      elp: 'Mati&egrave;res associ&eacute;es',
      gpelp: 'Groupes TD associ&eacute;s'
  };

  var symbol_navigate = "\u21B8";

  var highlight = function (text) {
      return "<span class='match'>" + text + "</span>";
  };

  var getDetails = function (item) {
      var details = [];

      if (item.mail && item.searchedTokenL === item.mail.toLowerCase()) {
	  details.push(highlight(item.mail));
      } else if (item.duplicateDisplayName) {
	  details.push(item.mail);
      }
      if (item.employeeType)
	  details.push(item.employeeType.join(" - "));
      if (item.supannRoleGenerique)
	  details.push(item.supannRoleGenerique.join(" - "));
      if (item['supannEntiteAffectation-ou']) {
	  var prev = details.pop();
	  details.push((prev ? prev + " - " : '') + item['supannEntiteAffectation-ou'].join(" - "));
      }
      if (item.departmentNumber) {
	  details.push((item.departmentNumber.count >= 2 ? "Disciplines : " : "Discipline : ") + item.departmentNumber.join(' - '));
      }
//      if (item.supannEtablissement)
//	  details.push(item.supannEtablissement.join(" - "));

      if (details.length) 
	  return "<div class='details'>" + details.join("<br>") + "</div>"
      else
	  return "";
  };

  var highlightMatched = function (text, searchedTokenL) {
	var textL = text.toLowerCase();
	var pos = textL.search(searchedTokenL);
	if (pos < 0) 
	    return null;
	else {
	    var endPos = pos + searchedTokenL.length;
	    return text.substring(0, pos) + 
		highlight(text.substring(pos, endPos)) +
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
	  uid = highlight(uid);
      } else if (item.cn.toLowerCase().indexOf(searchedTokenL) === 0)
	  displayName = highlightMatched(item.cn, searchedTokenL);
      else {
	  displayName = highlightMatched(item.displayName, searchedTokenL);
	  if (!displayName && item.mail) displayName = highlightMatched(item.mail, searchedTokenL);
	  if (!displayName) displayName = item.displayName;
      }

      if (display_uid)
	  displayName += " (" + uid + ")";

      return displayName;
  };

  var renderOneWarning = function(ul, msg) {
      return $("<li></li>").addClass("warning").append(msg).appendTo(ul);
  };

  var defaultWarningMsgs = {
    listeRouge_plural: "NB : des r&eacute;sultats ont &eacute;t&eacute; cach&eacute;s<br>&agrave; la demande des personnes.",
    listeRouge_one:    "NB : un r&eacute;sultat a &eacute;t&eacute; cach&eacute;<br>&agrave; la demande de la personne.",
}

  var renderWarningItem = function(ul, item, warningMsgs) {
      var li = $();
      if (item.nbListeRouge)
	  li = renderOneWarning(ul, 
	      item.nbListeRouge > 1 ? warningMsgs.listeRouge_plural : warningMsgs.listeRouge_one
	  );

      if (item.partialResults)
	  li = renderOneWarning(ul, "Votre recherche est limit&eacute;e &agrave; " + item.partialResults + " r&eacute;sultats.<br>Pour les autres r&eacute;sultats, veuillez affiner la recherche.");
      if (item.partialResultsNoFullSearch)
	  li = renderOneWarning(ul, "Votre recherche est limit&eacute;e.<br>Pour les autres r&eacute;sultats, veuillez affiner la recherche.");

      if (item.wsError)
	  li = renderOneWarning(ul, "Erreur web service");

      return li;
  };
  var myRenderItemRaw = function(ul, item, moreClass, warningMsgs, renderItemContent) {
	if (item.warning) 
	    return renderWarningItem(ul, item, warningMsgs);

	if (item.pre)
	    $("<li class='kind ui-menu-divider'><span>" + item.pre + "</span></li>").appendTo(ul);

	var content = renderItemContent(item);
      return $("<li></li>").addClass(item.odd_even ? "odd" : "even").addClass(moreClass)
	    .data("item.autocomplete", item)
	    .append("<a>" + content + "</a>")
	    .appendTo(ul);

  };
  var myRenderUserItem = function (warningMsgs) {
    return function (ul, item) {
      return myRenderItemRaw(ul, item, 'userItem', warningMsgs, function (item) {
	  return getNiceDisplayName(item) + getDetails(item);
      });
    };
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

  var groupByAttr = function(l, attrName) {
      var r = [];
      var subl, prevAttrVal;
      $.each(l, function (i, e) {
	  var attrVal = e[attrName] || '';	    
	  if (attrVal != prevAttrVal) {
	      subl = [];
	      r.push(subl);
	      prevAttrVal = attrVal;
	  }
	  subl.push(e);
	});
	return r;
  };

  var transformItems = function (items, idAttr, displayAttr, searchedToken) {
    var searchedTokenL = searchedToken.toLowerCase();
    var odd_even;
    $.each(items, function ( i, item ) {
	    item.label = item[displayAttr];
	    item.value = item[idAttr] || 'unknown';
	    item.searchedTokenL = searchedTokenL;
	    item.odd_even = odd_even = !odd_even;
    });
  };

  var transformUserItems = function (items, wantedAttr, searchedToken) {
      items = sortByAffiliation(items);

      var items_by_affiliation = groupByAttr(items, 'eduPersonPrimaryAffiliation');

      transformItems(items, wantedAttr, 'displayName', searchedToken);
      var r = [];
      $.each(items_by_affiliation, function (i, items) {
	// nb: "cn" is easer to compare since there is no accents. Two "displayName"s could be equal after removing accents.
	var cnOccurences = countOccurences($.map(items, function (item) { return item.cn }));
	var displayNameOccurences = countOccurences($.map(items, function (item) { return item.displayName }));
	$.each(items, function ( i, item ) {
	    if (i === 0) {
		var affiliation = item.eduPersonPrimaryAffiliation;
		item.pre = affiliation2text[affiliation] || "Divers" ;
	    }

	    if (displayNameOccurences[item.displayName] > 1 || cnOccurences[item.cn] > 1)
		item.duplicateDisplayName = true;
	});
	$.merge(r, items);
      });
      return r;
  };

  function handleEnterKey(input, disableEnterKey) {
      if (disableEnterKey)
          input.keydown(function(event){
	      var keyCode = $.ui.keyCode;
      	      switch( event.keyCode ) {
      	      case keyCode.ENTER:
	      case keyCode.NUMPAD_ENTER:
		      event.preventDefault();
		      event.stopPropagation();
	      }
          });
      else
          input.keyup(function(event){
              var keyCode = $.ui.keyCode;
              switch( event.keyCode ) {
              case keyCode.ENTER:
              case keyCode.NUMPAD_ENTER:
                  input.autocomplete('close');
              }
          });
  }

  function ui_autocomplete_data(input) {
      return input.data("ui-autocomplete") || input.data("autocomplete"); // compatibility with jquery-ui <= 1.8.x
  }

  var myOpen = function () {
      var menu = ui_autocomplete_data($(this)).menu.element;
      var menu_bottom = menu.position().top + menu.outerHeight();
      var window_bottom = $(window).scrollTop() + $(window).height();
      if (window_bottom < menu_bottom) {
	  var best_offset = $(window).scrollTop() + menu_bottom - window_bottom;
	  var needed_offset = $(this).offset().top
	  $('html,body').scrollTop(Math.min(needed_offset, best_offset));
      }
  };
    
  $.fn.autocompleteUser = function (searchUserURL, options) {
      if (!searchUserURL) throw "missing param searchUserURL";

      var settings = $.extend( 
	  { 'minLength' : 2,
	    'minLengthFullSearch' : 4,
	    'maxRows' : 10,
	    'wantedAttr' : 'uid',
	    'disableEnterKey': false,
	    'attrs' : attrs
	  }, options);

      var warningMsgs = $.extend(defaultWarningMsgs, settings.warningMsgs);

      var wsParams = $.extend({ 
	  maxRows: settings.maxRows, 
	  attrs: settings.attrs + "," + settings.wantedAttr
      }, settings.wsParams);

      var input = this;

      var source = function( request, response ) {
	  wsParams.token = request.term = request.term.trim();
	    $.ajax({
		url: searchUserURL,
		dataType: "jsonp",
		crossDomain: true, // needed if searchUserURL is CAS-ified or on a different host than application using autocompleteUser
		data: wsParams,
		error: function () {
		    // we should display on error. but we do not have a nice error to display
		    // the least we can do is to show the user the request is finished!
		    response([ { warning: true, wsError: true } ]);
		},
		success: function (dataAll) {
                    if (options.modifyResults) {
                        dataAll = options.modifyResults(dataAll, wsParams.token);
                    }
		    data = $.grep(dataAll, function (item, i) { 
			return item.displayName !== "supannListeRouge"; 
		    });
		    nbListeRouge = dataAll.length - data.length;

		    data = transformUserItems(data, settings.wantedAttr, request.term);

		    warning = { warning: true }
		    data.unshift(warning);
		    if (data.length >= settings.maxRows) {
			warning.partialResults = settings.maxRows;;
		    } else if (request.term.length < settings.minLengthFullSearch) {
			warning.partialResultsNoFullSearch = 1;
		    }
		    warning.nbListeRouge = nbListeRouge;

		    response(data);
		}
	    });
      };

      var params = {
	  minLength: settings.minLength,
	  source: source,
	  open: myOpen
      };

      if (settings.select) {
	  params.select = settings.select;
	  params.focus = function () {
	    // prevent update of <input>
	    return false;
	  };
      }

      handleEnterKey(input, settings.disableEnterKey);

      input.autocomplete(params);

      ui_autocomplete_data(input)._renderItem = myRenderUserItem(warningMsgs);

      // below is useful when going back on the search values
      input.click(function () {
      	  input.autocomplete("search");
      });

      return { wsParams: wsParams };
  };


  var transformGroupItems = function (items, wantedAttr, searchedToken) {
      transformItems(items, wantedAttr, 'name', searchedToken);
      var category;
      $.each(items, function ( i, item ) {
	    if (category != item.category) {
		category = item.category;
		item.pre = category2text[category || ""] || 'Autres types de groupes';
	    }
	});
  };

  var transformRoleGeneriqueItems = function (roles, activites, searchedToken) {
      $.each(roles, function ( i, item ) {
        item.category = 'supannRoleGenerique';
      });
      $.each(activites, function ( i, item ) {
        item.category = 'supannActivite';
      });
      var items = roles.concat(activites);
      items.sort(function (a, b) { return a.name.localeCompare(b.name) });
      transformItems(items, 'key', 'name', searchedToken);
      $.each(items, function ( i, item ) {
        if (i === 0) {
            item.pre = 'Fonctions';
        }
      });
      return items;
  }

  function object_values(o) {
      return $.map(o, function (e) { return e; })
  }

  // ["aa", "aaa", "ab"] => "a"
  function find_common_prefix(list){
      var A = list.slice(0).sort(), word1 = A[0], word2 = A[A.length-1];
      var len = word1.length, i= 0;
      while(i < len && word1.charAt(i)=== word2.charAt(i)) i++;
      return word1.substring(0, i);
  }

  // ["aa", "aaa", "ab"] => ["a", "aa", "b"]
  function remove_common_prefix(list) {
      var offset = find_common_prefix(list).length;
      return $.map(list, function(e) {
	  return e.substring(offset);
      });
  }

  var simplifySubGroups = function (subGroups) {
      if (subGroups.length <= 1) return;
      var names = $.map(subGroups, function (e) { return e.name });
      var offset = find_common_prefix(names).length;
      $.each(subGroups, function(i, e) {
	  e.name = e.name.substring(offset);
      });
  };
 
  var flattenSuperGroups = function (superGroups, groupId) {
      // remove current group
      delete superGroups[groupId];
      return sortByGroupCategory(object_values(superGroups));
  };

  var transformSubAndSuperGroups = function (items, wantedAttr) {
      var categoryText;
      var odd_even;
      $.each(items, function ( i, item ) {
	    item.label = item.name;
	    item.value = item[wantedAttr];

	    var categoryText_ = item.selected ? 'Selectionn&eacute;' : subAndSuper_category2text[item.category || ""] || 'Autres types de groupes';
	    if (categoryText != categoryText_) {
		item.pre = categoryText = categoryText_;
	    }
	    item.odd_even = odd_even = !odd_even;
	});
  };

    var onNavigate = function (input, settings) {
	var response = function (items) {
	    ui_autocomplete_data(input)._suggest(items);
	};
	return function (item) {
	    var allItems = [];
	    var cookAndAddReponses = function (items) {
		allItems = $.merge(allItems, items);
		transformSubAndSuperGroups(items, settings.wantedAttr);
		response(allItems);
	    };

	    var current = $.extend({}, item);
	    current.selected = true;
	    cookAndAddReponses([current]);

	    var wsParams = $.extend({ 
		key: item.key,
		depth: 99
	    }, settings.wsParams);

	    $.ajax({
		url: settings.subAndSuperGroupsURL,
		dataType: "jsonp",
		crossDomain: true, // needed if searchGroupURL is CAS-ified or on a different host than application using autocompleteUser
		data: wsParams,
		error: function () {
		    // we should display on error. but we do not have a nice error to display
		    // the least we can do is to show the user the request is finished!
		    response([ { warning: true, wsError: true } ]);
		},
		success: function (data) {
		    var subGroups = sortByGroupCategory(data.subGroups);
		    simplifySubGroups(subGroups);
		    var superGroups = flattenSuperGroups(data.superGroups, item.key);
		    var items = $.merge(subGroups, superGroups);
		    cookAndAddReponses(items);
		}
	    });
      };
    };

  var myRenderGroupItem = function (warningMsgs, navigate) {
     return function (ul, item) {
	if (item.warning) 
	     return renderWarningItem(ul, item, warningMsgs);

	if (item.pre)
	    $("<li class='kind'><span>" + item.pre + "</span></li>").appendTo(ul);

	var content = item.name;
        var li = $("<li></li>").addClass(item.odd_even ? "odd" : "even").addClass('groupItem')
	     .data("item.autocomplete", item);

	var button_navigate;
	if (navigate && !item.selected) {
	  button_navigate = $("<a style='display: inline' href='#'>" + symbol_navigate + "</a>").click(function (event) {
	    var item = $(this).closest("li").data("item.autocomplete");
	    navigate(item);
	    return false;
	  });
	  li.append($("<big>").append(button_navigate));
	}
        li.append($("<a style='display: inline' >")
		   .append(content + " &nbsp;"));
	li.appendTo(ul);
     };
  };

  function sortByGroupCategory (items) {
      return items.sort(function (a, b) {
	  var cmp = (category2order[a.category] || 99) - (category2order[b.category] || 99);
          return cmp ? cmp : a.name.localeCompare(b.name);
      });
  }

  $.fn.autocompleteGroup = function (searchGroupURL, options) {
      if (!searchGroupURL) throw "missing param searchGroupURL";

      var settings = $.extend( 
	  { 'minLength' : 3,
	    'maxRows' : 20,
	    'wantedAttr' : 'key',
	    'disableEnterKey': false
	  }, options);

      var warningMsgs = $.extend(defaultWarningMsgs, settings.warningMsgs);    
      
      var wsParams = $.extend({ 
	  maxRows: settings.maxRows
      }, settings.wsParams);

      var input = this;

      var source = function( request, response ) {
	  wsParams.token = request.term = request.term.trim();
	    $.ajax({
		url: searchGroupURL,
		dataType: "jsonp",
		crossDomain: true, // needed if searchGroupURL is CAS-ified or on a different host than application using autocompleteUser
		data: wsParams,
		error: function () {
		    // we should display on error. but we do not have a nice error to display
		    // the least we can do is to show the user the request is finished!
		    response([ { warning: true, wsError: true } ]);
		},
		success: function (data) {
		    data = sortByGroupCategory(data);
		    transformGroupItems(data, settings.wantedAttr, request.term);

		    warning = { warning: true }
		    data.push(warning);
		    if (data.length >= settings.maxRows) {
			warning.partialResults = settings.maxRows;;
		    }
		    response(data);
		}
	    });
      };

      var params = {
	  minLength: settings.minLength,
	  source: source,
	  open: myOpen
      };

      if (settings.select) {
	  params.select = settings.select;
	  params.focus = function () {
	    // prevent update of <input>
	    return false;
	  };
      }

      handleEnterKey(input, settings.disableEnterKey);

      input.autocomplete(params);

      var navigate = settings.subAndSuperGroupsURL && onNavigate(input, settings);
      ui_autocomplete_data(input)._renderItem = myRenderGroupItem(warningMsgs, navigate);

      // below is useful when going back on the search values
      input.click(function () {
      	  input.autocomplete("search");
      });
  };

  function myRenderUserOrGroupItem(warningMsgs) {
      return function (ul, item) {
      if (item && item.category === 'users')
          myRenderUserItem(warningMsgs)(ul, item);
      else
          myRenderGroupItem(warningMsgs)(ul, item);
      }
  }

  $.fn.autocompleteUserAndGroup = function (searchUserAndGroupURL, options) {
      if (!searchUserAndGroupURL) throw "missing param searchUserAndGroupURL";

      var settings = $.extend( 
	  { 'minLength' : 2,
	    'user_minLengthFullSearch' : 4,
	    'user_attrs' : attrs,
	    'maxRows' : 10,
            'group_minLength' : 2,
	    'disableEnterKey': false,
	  }, options);

      var warningMsgs = $.extend(defaultWarningMsgs, settings.warningMsgs);     
      
      var wsParams = $.extend({ 
	  maxRows: settings.maxRows,
	  user_attrs: settings.user_attrs
      }, settings.wsParams);

      var input = this;

      var source = function( request, response ) {
	  wsParams.token = request.term = request.term.trim();
	    $.ajax({
		url: searchUserAndGroupURL,
		dataType: "jsonp",
		crossDomain: true, // needed if searchGroupURL is CAS-ified or on a different host than application using autocompleteUser
		data: wsParams,
		error: function () {
		    // we should display on error. but we do not have a nice error to display
		    // the least we can do is to show the user the request is finished!
		    response([ { warning: true, wsError: true } ]);
		},
		success: function (data) {
                    if (settings.onSearchSuccess) data = settings.onSearchSuccess(data);
                    var users = $.grep(data.users, function (item, i) {
			return item.displayName !== "supannListeRouge"; 
		    });
		    var nbListeRouge = users.length - data.users.length;

                    $.each(users, function (i, item) { item.category = 'users'; });                    
		    users = transformUserItems(users, 'uid', request.term);
		    data.groups = sortByGroupCategory(data.groups)
		    transformGroupItems(data.groups, 'key', request.term);

            var roles = transformRoleGeneriqueItems(data.supannRoleGenerique || [], data.supannActivite || [], 'key', request.term);
            
		    warning = { warning: true }
                    var l = users.concat(roles, data.groups);
		    l.push(warning);
		    if (users.length >= settings.maxRows || data.groups.length >= settings.maxRows) {
			warning.partialResults = settings.maxRows;;
		    } else if (request.term.length < settings.user_minLengthFullSearch) {
			warning.partialResultsNoFullSearch = 1;
		    }
		    warning.nbListeRouge = nbListeRouge;
                   
		    response(l);
		}
	    });
      };

      var params = {
	  minLength: settings.minLength,
	  source: source,
	  open: myOpen
      };

      if (settings.select) {
	  params.select = settings.select;
	  params.focus = function () {
	    // prevent update of <input>
	    return false;
	  };
      }

      handleEnterKey(input, settings.disableEnterKey);

      input.autocomplete(params);

      ui_autocomplete_data(input)._renderItem = myRenderUserOrGroupItem(warningMsgs);

      // below is useful when going back on the search values
      input.click(function () {
      	  input.autocomplete("search");
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
