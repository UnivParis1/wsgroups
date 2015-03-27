(function ($) {
"use strict";

var baseURL = "https://ticetest.univ-paris1.fr/wsgroups";
var searchUserURL = baseURL + '/searchUserCAS';
var lastLoginsUrl = baseURL + '/userLastLogins';
var showExtendedInfo = undefined; showExtendedInfo = true;
var currentUser = undefined;

function parse_attrs_text(l) {
    return $.map(l, function (attr_text) {
	var r = attr_text && attr_text.match(/(.*?): (.*)/);
	if (!r) console.error("invalid attr_text " + attr_text);
	return r && { attr: r[1], text: r[2] };
    });
}

var main_attrs_labels = [ [
    'Person: Personne',

    'employeeType: Type',
    'Fonctions: Fonction(s)',
    'supannEntiteAffectation-all: Affectation(s)',
    'Affiliation: Affiliation',
    'businessCategory: Catégorie',

    'mail: Mail',
    'mailAlternateAddress: Mail secondaire',
    'supannAutreMail: Mail secondaire',
    'supannMailPerso: Mail perso',
    'MailDelivery: Messagerie',

    'eduPersonOrgUnitDN: Formation(s)',

    //les attributs renvoyant des DN
    'seeAlso: Rattachement',
    'supannParrainDN-all: Parrainé par',

    // les attributs faisant appel aux tables de nomenclature LDAP
    'supannEtablissement: Etablissement tiers',

    'supannEtuInscription-all: Inscription(s)',
    'supannEtuInscription-prev: Ancienne(s) inscription(s)',
    'supannEtuEtape: Etape',
    'supannEtuAnneeInscription: Année',
    'eduPersonOrgUnitDN: Affectation(s)',

    'telephoneNumber: Tél',
    'mobile: Tél mobile',
    'pager: Tél mobile',
    'supannAutreTelephone: Tél secondaire',
    'facsimileTelephoneNumber: Fax',
],
[
    'supannActivite-REFERENS: Emploi type',
    'supannActivite-other: Discipline(s)',
    //'departmentNumber: Discipline(s)', // redundant

    'labeledURI: Web',

    'buildingName: Site',
    'up1FloorNumber: Etage',
    'roomNumber: Bureau',
    'postalAddress: Adresse postale',

    //'cn: cn',
],
[
    'up1Roles: Mail(s) de fonction',
    'Identifiers: Identifiant(s)',
    'shadowExpire: Expire le',
    'accountStatus: Etat du compte',
    'Account: Compte',

    //'supannRefID: RefId',
    'up1KrbPrincipal: Kerb',
    'supannListeRouge: ' + important('Liste rouge'),
    'createTimestamp: createTimestamp',
    'modifyTimestamp: modifyTimestamp',
],
[
    'memberOf: Groupes',
]
];

var sub_attrs_labels = {
    'Identifiers': 
    [ 'uid: '
    , 'supannAliasLogin: Login'
    , 'uidNumber: UID'
    , 'supannEmpId: Emp'
    , 'supannEtuId: Etu'
    , 'supannCodeINE: INE'
    , 'employeeNumber: Code-barre'
  //, 'eduPersonPrincipalName: EPPN'
    ]
};
    

var eduPersonAffiliation_valnames = {
	'student': "étudiant",
	'staff': "personnel technique ou administratif",
	'teacher': "enseignant",
	'researcher': "chercheur",
	'affiliate': "partenaire extérieur",
	'alum': "ancien étudiant",
	'emeritus': "professeur émérite",
	'retired': "retraité",
	'employee': "employé",
        'member': 'membre',
	'internal': "compte interne",
};

function buildingNameToUrl(buildingName) {
    var toTrigramme = {
// missing: centre-arago centre-bicetre centre-de-sceaux centre-meudon centre-nanterre centre-sorbonne-1 centre-sorbonne-47 centre-villejuif fontenay-aux-roses maison-des-sciences-economiques rue-regnault 
  "Centre 17 rue de Tolbiac"        : '17t',
  "Maison internationale"	    : 'ara',
  "Centre Bourg-la-Reine"	    : 'blr',
  "Sainte Barbe (Institut Tunc)"    : 'brb',
  "Centre Broca"		    : 'brc',
  "Centre Port-Royal René Cassin"   : 'cas',
  "Centre Cujas"		    : 'cuj',
  "Centre rue du Four"		    : 'dfo',
  "Centre Michelet"		    : 'iaa',
  "Centre Malher"		    : 'mah',
  "Maison des Sciences Économiques" : 'mse',
  "Centre Pierre Mendès France"	    : 'pmf',
  "Centre Panthéon"		    : 'pth',
  "Centre Sorbonne"		    : 'srb',
  "Centre Saint Charles"	    : 'stc',
  "Institut de Géographie"	    : 'stj',
  "Centre Thénard"		    : 'thn',
  "Centre rue d'Ulm"		    : 'ulm',
  "Centre Valette"		    : 'val',
 
  "centre Albert Chatelet" 	    : 'ach'
}

    // found on page http://www.univ-paris1.fr/universite/campus/ using:
    // $.map($("#c531163").find("a"), function (e) { return e.href.replace("http://www.univ-paris1.fr/universite/campus/detail-campus/", "") })
    var trigramme = toTrigramme[buildingName];
    return trigramme && ("http://www.univ-paris1.fr/universite/campus/detail-campus/" + trigramme + "/");
}

function important(s) {
    return "<span class='important'>" + s + "</span>";
}

var attr2valnames = {
    'accountStatus': {
	'active': "ACTIF",
	'noaccess': important("VERROUILLE"),
	'disabled': important("DESACTIVE"),
	'deleted': important("PURGE"),
    },
    'shadowFlag': {
	2: "DOUBLON",
	8: "DECEDE",
//	9: "VERROUILLE",
    },
    //'businessCategory': {
    //	'administration': "administration",
    //	'pedagogy': "pédagogie",
    //	'library': "bibliothèque",
    //	'trade union': "syndicat",
    //	'research': "recherche",
    //	'council': "conseils et commissions",
    //	'web': "web",
    //	'test': "test",
    //},
    'objectClass': {
	'up1Person': 'Personne',
	'up1Role': 'Compte de fonction',
    }    
};

if (!Array.prototype.indexOf) {
    Array.prototype.indexOf = function (searchElement, fromIndex) {
      if ( this === undefined || this === null ) {
        throw new TypeError( '"this" is null or not defined' );
      }

      var length = this.length >>> 0; // Hack to convert object.length to a UInt32

      fromIndex = +fromIndex || 0;

      if (Math.abs(fromIndex) === Infinity) {
        fromIndex = 0;
      }

      if (fromIndex < 0) {
        fromIndex += length;
        if (fromIndex < 0) {
          fromIndex = 0;
        }
      }

      for (;fromIndex < length; fromIndex++) {
        if (this[fromIndex] === searchElement) {
          return fromIndex;
        }
      }

      return -1;
    };
}

var diacriticsMap = [
      {'base':'A', 'letters':/[\u0041\u24B6\uFF21\u00C0\u00C1\u00C2\u1EA6\u1EA4\u1EAA\u1EA8\u00C3\u0100\u0102\u1EB0\u1EAE\u1EB4\u1EB2\u0226\u01E0\u01DE\u1EA2\u00C5\u01FA\u01CD\u0200\u0202\u1EA0\u1EAC\u1EB6\u1E00\u0104\u023A\u2C6F]/g},
      {'base':'AA','letters':/[\uA732]/g},
      {'base':'AE','letters':/[\u00C4\u00C6\u01FC\u01E2]/g},
      {'base':'AO','letters':/[\uA734]/g},
      {'base':'AU','letters':/[\uA736]/g},
      {'base':'AV','letters':/[\uA738\uA73A]/g},
      {'base':'AY','letters':/[\uA73C]/g},
      {'base':'B', 'letters':/[\u0042\u24B7\uFF22\u1E02\u1E04\u1E06\u0243\u0182\u0181]/g},
      {'base':'C', 'letters':/[\u0043\u24B8\uFF23\u0106\u0108\u010A\u010C\u00C7\u1E08\u0187\u023B\uA73E]/g},
      {'base':'D', 'letters':/[\u0044\u24B9\uFF24\u1E0A\u010E\u1E0C\u1E10\u1E12\u1E0E\u0110\u018B\u018A\u0189\uA779]/g},
      {'base':'DZ','letters':/[\u01F1\u01C4]/g},
      {'base':'Dz','letters':/[\u01F2\u01C5]/g},
      {'base':'E', 'letters':/[\u0045\u24BA\uFF25\u00C8\u00C9\u00CA\u1EC0\u1EBE\u1EC4\u1EC2\u1EBC\u0112\u1E14\u1E16\u0114\u0116\u00CB\u1EBA\u011A\u0204\u0206\u1EB8\u1EC6\u0228\u1E1C\u0118\u1E18\u1E1A\u0190\u018E]/g},
      {'base':'F', 'letters':/[\u0046\u24BB\uFF26\u1E1E\u0191\uA77B]/g},
      {'base':'G', 'letters':/[\u0047\u24BC\uFF27\u01F4\u011C\u1E20\u011E\u0120\u01E6\u0122\u01E4\u0193\uA7A0\uA77D\uA77E]/g},
      {'base':'H', 'letters':/[\u0048\u24BD\uFF28\u0124\u1E22\u1E26\u021E\u1E24\u1E28\u1E2A\u0126\u2C67\u2C75\uA78D]/g},
      {'base':'I', 'letters':/[\u0049\u24BE\uFF29\u00CC\u00CD\u00CE\u0128\u012A\u012C\u0130\u00CF\u1E2E\u1EC8\u01CF\u0208\u020A\u1ECA\u012E\u1E2C\u0197]/g},
      {'base':'J', 'letters':/[\u004A\u24BF\uFF2A\u0134\u0248]/g},
      {'base':'K', 'letters':/[\u004B\u24C0\uFF2B\u1E30\u01E8\u1E32\u0136\u1E34\u0198\u2C69\uA740\uA742\uA744\uA7A2]/g},
      {'base':'L', 'letters':/[\u004C\u24C1\uFF2C\u013F\u0139\u013D\u1E36\u1E38\u013B\u1E3C\u1E3A\u0141\u023D\u2C62\u2C60\uA748\uA746\uA780]/g},
      {'base':'LJ','letters':/[\u01C7]/g},
      {'base':'Lj','letters':/[\u01C8]/g},
      {'base':'M', 'letters':/[\u004D\u24C2\uFF2D\u1E3E\u1E40\u1E42\u2C6E\u019C]/g},
      {'base':'N', 'letters':/[\u004E\u24C3\uFF2E\u01F8\u0143\u00D1\u1E44\u0147\u1E46\u0145\u1E4A\u1E48\u0220\u019D\uA790\uA7A4]/g},
      {'base':'NJ','letters':/[\u01CA]/g},
      {'base':'Nj','letters':/[\u01CB]/g},
      {'base':'O', 'letters':/[\u004F\u24C4\uFF2F\u00D2\u00D3\u00D4\u1ED2\u1ED0\u1ED6\u1ED4\u00D5\u1E4C\u022C\u1E4E\u014C\u1E50\u1E52\u014E\u022E\u0230\u022A\u1ECE\u0150\u01D1\u020C\u020E\u01A0\u1EDC\u1EDA\u1EE0\u1EDE\u1EE2\u1ECC\u1ED8\u01EA\u01EC\u00D8\u01FE\u0186\u019F\uA74A\uA74C]/g},
      {'base':'OE','letters':/[\u00D6\u0152]/g},
      {'base':'OI','letters':/[\u01A2]/g},
      {'base':'OO','letters':/[\uA74E]/g},
      {'base':'OU','letters':/[\u0222]/g},
      {'base':'P', 'letters':/[\u0050\u24C5\uFF30\u1E54\u1E56\u01A4\u2C63\uA750\uA752\uA754]/g},
      {'base':'Q', 'letters':/[\u0051\u24C6\uFF31\uA756\uA758\u024A]/g},
      {'base':'R', 'letters':/[\u0052\u24C7\uFF32\u0154\u1E58\u0158\u0210\u0212\u1E5A\u1E5C\u0156\u1E5E\u024C\u2C64\uA75A\uA7A6\uA782]/g},
      {'base':'S', 'letters':/[\u0053\u24C8\uFF33\u1E9E\u015A\u1E64\u015C\u1E60\u0160\u1E66\u1E62\u1E68\u0218\u015E\u2C7E\uA7A8\uA784]/g},
      {'base':'T', 'letters':/[\u0054\u24C9\uFF34\u1E6A\u0164\u1E6C\u021A\u0162\u1E70\u1E6E\u0166\u01AC\u01AE\u023E\uA786]/g},
      {'base':'TZ','letters':/[\uA728]/g},
      {'base':'U', 'letters':/[\u0055\u24CA\uFF35\u00D9\u00DA\u00DB\u0168\u1E78\u016A\u1E7A\u016C\u01DB\u01D7\u01D5\u01D9\u1EE6\u016E\u0170\u01D3\u0214\u0216\u01AF\u1EEA\u1EE8\u1EEE\u1EEC\u1EF0\u1EE4\u1E72\u0172\u1E76\u1E74\u0244]/g},
      {'base':'UE','letters':/[\u00DC]/g},
      {'base':'V', 'letters':/[\u0056\u24CB\uFF36\u1E7C\u1E7E\u01B2\uA75E\u0245]/g},
      {'base':'VY','letters':/[\uA760]/g},
      {'base':'W', 'letters':/[\u0057\u24CC\uFF37\u1E80\u1E82\u0174\u1E86\u1E84\u1E88\u2C72]/g},
      {'base':'X', 'letters':/[\u0058\u24CD\uFF38\u1E8A\u1E8C]/g},
      {'base':'Y', 'letters':/[\u0059\u24CE\uFF39\u1EF2\u00DD\u0176\u1EF8\u0232\u1E8E\u0178\u1EF6\u1EF4\u01B3\u024E\u1EFE]/g},
      {'base':'Z', 'letters':/[\u005A\u24CF\uFF3A\u0179\u1E90\u017B\u017D\u1E92\u1E94\u01B5\u0224\u2C7F\u2C6B\uA762]/g},
      {'base':'a', 'letters':/[\u0061\u24D0\uFF41\u1E9A\u00E0\u00E1\u00E2\u1EA7\u1EA5\u1EAB\u1EA9\u00E3\u0101\u0103\u1EB1\u1EAF\u1EB5\u1EB3\u0227\u01E1\u01DF\u1EA3\u00E5\u01FB\u01CE\u0201\u0203\u1EA1\u1EAD\u1EB7\u1E01\u0105\u2C65\u0250]/g},
      {'base':'aa','letters':/[\uA733]/g},
      {'base':'ae','letters':/[\u00E4\u00E6\u01FD\u01E3]/g},
      {'base':'ao','letters':/[\uA735]/g},
      {'base':'au','letters':/[\uA737]/g},
      {'base':'av','letters':/[\uA739\uA73B]/g},
      {'base':'ay','letters':/[\uA73D]/g},
      {'base':'b', 'letters':/[\u0062\u24D1\uFF42\u1E03\u1E05\u1E07\u0180\u0183\u0253]/g},
      {'base':'c', 'letters':/[\u0063\u24D2\uFF43\u0107\u0109\u010B\u010D\u00E7\u1E09\u0188\u023C\uA73F\u2184]/g},
      {'base':'d', 'letters':/[\u0064\u24D3\uFF44\u1E0B\u010F\u1E0D\u1E11\u1E13\u1E0F\u0111\u018C\u0256\u0257\uA77A]/g},
      {'base':'dz','letters':/[\u01F3\u01C6]/g},
      {'base':'e', 'letters':/[\u0065\u24D4\uFF45\u00E8\u00E9\u00EA\u1EC1\u1EBF\u1EC5\u1EC3\u1EBD\u0113\u1E15\u1E17\u0115\u0117\u00EB\u1EBB\u011B\u0205\u0207\u1EB9\u1EC7\u0229\u1E1D\u0119\u1E19\u1E1B\u0247\u025B\u01DD]/g},
      {'base':'f', 'letters':/[\u0066\u24D5\uFF46\u1E1F\u0192\uA77C]/g},
      {'base':'g', 'letters':/[\u0067\u24D6\uFF47\u01F5\u011D\u1E21\u011F\u0121\u01E7\u0123\u01E5\u0260\uA7A1\u1D79\uA77F]/g},
      {'base':'h', 'letters':/[\u0068\u24D7\uFF48\u0125\u1E23\u1E27\u021F\u1E25\u1E29\u1E2B\u1E96\u0127\u2C68\u2C76\u0265]/g},
      {'base':'hv','letters':/[\u0195]/g},
      {'base':'i', 'letters':/[\u0069\u24D8\uFF49\u00EC\u00ED\u00EE\u0129\u012B\u012D\u00EF\u1E2F\u1EC9\u01D0\u0209\u020B\u1ECB\u012F\u1E2D\u0268\u0131]/g},
      {'base':'j', 'letters':/[\u006A\u24D9\uFF4A\u0135\u01F0\u0249]/g},
      {'base':'k', 'letters':/[\u006B\u24DA\uFF4B\u1E31\u01E9\u1E33\u0137\u1E35\u0199\u2C6A\uA741\uA743\uA745\uA7A3]/g},
      {'base':'l', 'letters':/[\u006C\u24DB\uFF4C\u0140\u013A\u013E\u1E37\u1E39\u013C\u1E3D\u1E3B\u017F\u0142\u019A\u026B\u2C61\uA749\uA781\uA747]/g},
      {'base':'lj','letters':/[\u01C9]/g},
      {'base':'m', 'letters':/[\u006D\u24DC\uFF4D\u1E3F\u1E41\u1E43\u0271\u026F]/g},
      {'base':'n', 'letters':/[\u006E\u24DD\uFF4E\u01F9\u0144\u00F1\u1E45\u0148\u1E47\u0146\u1E4B\u1E49\u019E\u0272\u0149\uA791\uA7A5]/g},
      {'base':'nj','letters':/[\u01CC]/g},
      {'base':'o', 'letters':/[\u006F\u24DE\uFF4F\u00F2\u00F3\u00F4\u1ED3\u1ED1\u1ED7\u1ED5\u00F5\u1E4D\u022D\u1E4F\u014D\u1E51\u1E53\u014F\u022F\u0231\u022B\u1ECF\u0151\u01D2\u020D\u020F\u01A1\u1EDD\u1EDB\u1EE1\u1EDF\u1EE3\u1ECD\u1ED9\u01EB\u01ED\u00F8\u01FF\u0254\uA74B\uA74D\u0275]/g},
      {'base':'oe','letters': /[\u00F6\u0153]/g},
      {'base':'oi','letters':/[\u01A3]/g},
      {'base':'ou','letters':/[\u0223]/g},
      {'base':'oo','letters':/[\uA74F]/g},
      {'base':'p','letters':/[\u0070\u24DF\uFF50\u1E55\u1E57\u01A5\u1D7D\uA751\uA753\uA755]/g},
      {'base':'q','letters':/[\u0071\u24E0\uFF51\u024B\uA757\uA759]/g},
      {'base':'r','letters':/[\u0072\u24E1\uFF52\u0155\u1E59\u0159\u0211\u0213\u1E5B\u1E5D\u0157\u1E5F\u024D\u027D\uA75B\uA7A7\uA783]/g},
      {'base':'s','letters':/[\u0073\u24E2\uFF53\u015B\u1E65\u015D\u1E61\u0161\u1E67\u1E63\u1E69\u0219\u015F\u023F\uA7A9\uA785\u1E9B]/g},
      {'base':'ss','letters':/[\u00DF]/g},
      {'base':'t','letters':/[\u0074\u24E3\uFF54\u1E6B\u1E97\u0165\u1E6D\u021B\u0163\u1E71\u1E6F\u0167\u01AD\u0288\u2C66\uA787]/g},
      {'base':'tz','letters':/[\uA729]/g},
      {'base':'u','letters':/[\u0075\u24E4\uFF55\u00F9\u00FA\u00FB\u0169\u1E79\u016B\u1E7B\u016D\u01DC\u01D8\u01D6\u01DA\u1EE7\u016F\u0171\u01D4\u0215\u0217\u01B0\u1EEB\u1EE9\u1EEF\u1EED\u1EF1\u1EE5\u1E73\u0173\u1E77\u1E75\u0289]/g},
      {'base':'ue','letters':/[\u00FC]/g},
      {'base':'v','letters':/[\u0076\u24E5\uFF56\u1E7D\u1E7F\u028B\uA75F\u028C]/g},
      {'base':'vy','letters':/[\uA761]/g},
      {'base':'w','letters':/[\u0077\u24E6\uFF57\u1E81\u1E83\u0175\u1E87\u1E85\u1E98\u1E89\u2C73]/g},
      {'base':'x','letters':/[\u0078\u24E7\uFF58\u1E8B\u1E8D]/g},
      {'base':'y','letters':/[\u0079\u24E8\uFF59\u1EF3\u00FD\u0177\u1EF9\u0233\u1E8F\u00FF\u1EF7\u1E99\u1EF5\u01B4\u024F\u1EFF]/g},
      {'base':'z','letters':/[\u007A\u24E9\uFF5A\u017A\u1E91\u017C\u017E\u1E93\u1E95\u01B6\u0225\u0240\u2C6C\uA763]/g}
];

if ($.fn.appendText) throw "InternalError: jquery appendText already exists";
$.fn.appendText = function(text) {
    return this.append(document.createTextNode(text));
};

function jqueryAppendMany(elt, list, separator) {
    if (!separator) separator = '';
    $.each(list, function (i, e) {
	if (i > 0) elt.append(separator);
	elt.append(e);
    });
    return elt;
}
function jqueryAppendManyText(elt, list, separator) {
    if (!separator) separator = '';
    $.each(list, function (i, e) {
	if (i > 0) elt.append(separator);
	elt.appendText(e);
    });
    return elt;
}
function appendWrappedText(elt, wrappedText) {
    return jqueryAppendManyText(elt, wrappedText.split(/\n+/), "<br>");
}
function spanFromList(list, separator){
    return jqueryAppendMany($("<span>"), list, separator);
}

function asciifie(s) {
    if (!s || $.isArray(s)) return s;
    $.each(diacriticsMap, function (i, v) {
	s = s.replace(v.letters, v.base);
    });
    return s;
}

function rejectEmpty(l) {
    return $.grep(l, function (v) { return v });
}

function partition(l, f) {
    var l1 = [];
    var l2 = [];
    $.each(l, function (i, e) {
	if (f(e)) l1.push(e); else l2.push(e);
    });
    return [l1, l2];
}

function flattenFailsafe(l) {
    var r = [];
    $.each(l, function (i, subl) {
	if (subl) $.merge(r, subl);
    });
    //console.log(r);
    return r;
}

function arraySubstraction(a, b) {
    return $.grep(a, function(e) {
        return b.indexOf(e) === -1;
    });
}

function capitalize (s) {
    return s.toLowerCase().replace(/(?:^|\s)\S/g, function(a) { return a.toUpperCase(); });
}

function formagtime(time) {
    var m = time.match(/([0-9]{4})([0-9]{2})([0-9]{2})[0-9]{6}Z?$/);
    return m ? m[3] + "/" + m[2] + "/" + m[1] : "(date invalide " + time + ")";
}

function leftPadZero(number, size) {
    number = number.toString();
    while (number.length < size) number = "0" + number;
    return number;
}

function formatDateRaw(d) {
    return leftPadZero(d.getDate(), 2) + "/" + leftPadZero(d.getMonth() + 1, 2) + "/" + d.getFullYear();
}

function formatDateTime(epoch) {
    var d = new Date(epoch * 1000);
    return "le " + formatDateRaw(d) + " à " + formatTimeHHhMM(d);
}
function formatTimeHHhMM(d) {
    return leftPadZero(d.getHours(), 2) + "h" + leftPadZero(d.getMinutes(), 2);
}

function formadate(epoch) {
    if (!epoch) return "(date inconnue)";
    var d = new Date(epoch * 24 * 3600 * 1000);
    return formatDateRaw(d);
}

function formadelai(date1, date2) {
    var delai = date2 - date1;
    return delai < 90 ? Math.round(delai) + " jours" :
	delai < 1000 ? Math.round(delai /30.5) + " mois" :
	Math.round(delai/365.2421875) + " ans";
}

function format_timestamp(timestamp) {
    var date = timestamp.replace(/(....)(..)(..)(..)(..)(.*)/, "$1-$2-$3T$4:$5:$6");
    var d = new Date(date);
    var text = "le " + formatDateRaw(d) + " à " + formatTimeHHhMM(d);
    return $("<span>", { title: timestamp }).text(text);
}

function compute_MailDelivery(info) {
    var fwd = info.mailForwardingAddress;
    var is_copy = $.inArray('mailbox', info.mailDeliveryOption) != -1;
    return (is_copy ? "copies vers " : "redirigée vers ") + 
	(fwd[0] === 'supannListeRouge' ? "une adresse mail" : fwd.join(", "));
}

function compute_Affiliation(info, showExtendedInfo) {
    var valnames = eduPersonAffiliation_valnames;
    var Affiliation = formatValues(valnames, info.eduPersonPrimaryAffiliation)
	|| spanFromList([important(info.eduPersonPrimaryAffiliation ? info.eduPersonPrimaryAffiliation + "??" : 'MANQUANTE')]);
    if (info.eduPersonAffiliation) {	
	var notWanted = $.merge([info.eduPersonPrimaryAffiliation],
				showExtendedInfo ? [] : [ 'employee', 'member' ]);
	var other = arraySubstraction(info.eduPersonAffiliation, notWanted);
	var fv = formatValues(valnames, other);
	if (fv) jqueryAppendMany(Affiliation, [" (secondaires: ", fv, ")"]);
    }
    return Affiliation;
}

function compute_Fonctions(info) {
    var list = flattenFailsafe([info.supannRoleGenerique, info.description, info.info]);
    return list.length && jqueryAppendManyText($("<span>"), list, "<br>");
}

function format_supannActivite(all, fInfo, showExtendedInfo) {
    var format_it = function (e) {
	return $("<span>", { title: e.key }).text(e.name);
    };
    var ll = partition(all, function (e) { return e.key.match(/^{REFERENS}/) });
    if (ll[0].length)
	fInfo['supannActivite-REFERENS'] = spanFromList($.map(ll[0], format_it), '<br>');
    if (ll[1].length)
	fInfo['supannActivite-other'] = spanFromList($.map(ll[1], format_it), '<br>');
}

function format_supannEtuInscriptionAll(all, fInfo, showExtendedInfo) {

    var anneeinsc = Math.max.apply(null, $.map(all, function (e) { return e.anneeinsc }));
    var ll = partition(all, function (e) { return e.anneeinsc == anneeinsc });
    var curr = ll[0], prev = ll[1];

    var format_it = function (e) { 
	var text = showExtendedInfo ? e.etape + " (" + e.anneeinsc + ")" : e.etape;
	var title = [];
	$.each(e, function (k,v) { 
	    if (showExtendedInfo ? k !== 'etape' : k === 'regimeinsc' || k === 'anneeinsc') title.push(k + ": " + v);
	});
	return $("<span>", { title: title.join(" ,  ") }).text(text); 
    };
    if (curr.length)
	fInfo['supannEtuInscription-all'] = spanFromList($.map(curr, format_it), "<br>");
    if (prev.length && showExtendedInfo) 
	fInfo['supannEtuInscription-prev'] = spanFromList($.map(prev, format_it), "<br>");
}

function format_memberOf(all) {
    all = all.sort(function (a, b) { 
	return a.key.toLowerCase().localeCompare(b.key.toLowerCase());
    });
    return spanFromList($.map(all, function (e) {
	return $("<span>").text(e.key + " : " + (e.description || ''));
    }), "<br>");
}

function format_shadowExpire(info) {
    var today = todayEpochDay();
    var delta = info.shadowExpire <= today ? important("EXPIRE") : formadelai(today, info.shadowExpire);
    return formadate(info.shadowExpire) + " (dans " + delta + ")";
}

function compute_Person(info, showExtendedInfo) {
    var person;
       if ($.isArray(info.objectClass) && $.grep(info.objectClass, function (v) { return v === "up1Person" })) {
	   person = (info.supannCivilite ? (info.supannCivilite + " " + info.displayName) : info.displayName) || "<INCONNU>";
       }

    if (!person) return undefined;

	var birthName = info.up1BirthName;
	if (birthName === info.sn) birthName = '';
	if (birthName || info.up1BirthDay) {
	    var nee = info.supannCivilite === "M." ? "né" :
		info.supannCivilite.match(/Mme|Mlle/) ? "née" : "né(e)";
	    person = person + ", " + nee +
		(birthName ? " " + birthName : '') +
		(info.up1BirthDay ? " le " + formagtime(info.up1BirthDay) : '');
	}

    var Person = $("<span>").text(person); 
    if (showExtendedInfo) {
	Person.attr('title', 'Prénom : ' + info.givenName + ", Nom : " + info.sn + ", Complet : " + info.cn);
    }
    return Person;
}

function compute_Identifiers(info) {
    if (info.supannAliasLogin && info.supannAliasLogin === info.uid)
	delete info.supannAliasLogin;
    
    return rejectEmpty($.map(parse_attrs_text(sub_attrs_labels.Identifiers), function (e) {
	var v = info[e.attr];
	return v && (e.text ? e.text + ": " + v : v);
    }));
}

function todayEpochDay() {
    return new Date().getTime() / 1000 / 3600 / 24;
}

function formatValues(valnames, v) {
    if ($.isArray(v)) {
	var fv = rejectEmpty($.map(v, function (val) { return valnames[val] }));
	return fv.length > 0 ? spanFromList(fv, ", ") : null;
    } else if (valnames[v]) {
	return spanFromList([valnames[v]]);
    } else {
	return;
    }
}

function formatSomeUserValues(info, fInfo) {
    $.each(info, function (attr, v) {
	var valnames = attr2valnames[attr];
	if (valnames) {
	    var fv = formatValues(valnames, v);
	    if (fv) fInfo[attr] = fv;
	}
    });
}

function formatLastLogins(info, data, div) {
    var since = data.since;
    var list = data.list.reverse().slice();
    var lastErrs = [];
    while (list.length && list[0].error) {
	lastErrs.push(list.shift());
    }
    if (list.length) {
	div.text(", dernier login " + formatDateTime(list[0].time));
	if (lastErrs.length)
	    div.append(important(", " + lastErrs.length + " échecs depuis"));
    } else if (lastErrs.length) {
	div.append(", " + important(lastErrs.length + " login en échecs depuis " + formatDateTime(lastErrs.reverse()[0].time)));
    } else {
	if (info.accountStatus === "active") 
	    div.text(", aucune tentative de login depuis " + formatDateTime(since));
    }
    if (data.list.length) {
	var details = $("<div class='vertical-scroll hidden'>");
	$.each(data.list, function (v, e) {
	    var t = formatDateTime(e.time) + " : " + (e.error || "SUCCESS") + " <small>(ip = " + e.ip + " )</small>";
	    details.append(t + '<br>');
	})
	div.append($("<span class='clickable'>").append(" <small>details</small>").click(function () { details.toggleClass("hidden") }));
	div.append(details);
    }
}

function get_lastLogins(info, infoDiv) {
    var infoDiv = $("<span>");
    $.ajax({
	url: lastLoginsUrl,
	dataType: "jsonp",
	crossDomain: true, // needed if url is CAS-ified or on a different host than application using autocompleteUser
	data: { login: info.supannAliasLogin || info.uid },
	error: function () {
	    infoDiv.text("Erreur web service");
	},
	success: function (data) {
	    if (data.length == 0) {
		infoDiv.text("user not found (??)");
	    } else if (data.length > 1) {
		infoDiv.text("internal error (multiple user found)");
	    } else {
		infoDiv.empty().append(formatLastLogins(info, data, infoDiv));
	    }
	}
    });
    return infoDiv;
}

function compute_Account_and_accountStatus(info, fInfo) {
    if (info.up1KrbPrincipal) {
	$.each(info.up1KrbPrincipal, function (i, krb) {
	    if (info.krbrealm && krb.split('@')[1] != krbrealm) {
		info.Account = "Approbation inter-domaine KERBEROS " + krb;
	    } else {
		var val = undefined; //krbgetusers(krb);
		if (val && val[krb]) {
		    info.Account = "KERBEROS, mot de passe";
		    //push @vals, ("changé le ".formadate($val->{$krb}{'lastchng'})) if $val->{$krb}{'lastchng'};
		    //push @vals, ("expire le ".formadate($val->{$krb}{'expire'})) if $val->{$krb}{'expire'} && $val->{$krb}{'expire'} != ($expire||0);
		    //push @vals, ("SANS EXPIRATION") if ! $val->{$krb}{'expire'} && $expire;
		    //push @vals, ("VERROUILLE") if $val->{$krb}{'locked'};
		    //print join(", ",@vals)."\n";
		}
	    }
	   });
    } else {
	if ( (!info.shadowExpire || info.shadowExpire > todayEpochDay()) && !info.accountStatus) {
	    fInfo.accountStatus = spanFromList([important('NON ACTIVE')]);
	}
	if (info.shadowLastChange) {
	    fInfo.Account = important("LDAP") + ", mot de passe changé le " + formadate(info.shadowLastChange);
	}
    }
    if (!info.accountStatus || info.accountStatus === "active") {
	if (!info.eduPersonAffiliation)
	    fInfo.accountStatus.append(" (" + important('il manque eduPersonAffiliation') + ")");
    }
    if (info.allowExtendedInfo > 1)
	fInfo.accountStatus.append(get_lastLogins(info));

    if (fInfo.shadowFlag) fInfo.accountStatus.append(" (" + fInfo.shadowFlag + ")");
}

var role2text = {
    "manager": "responsable",
    "roleOccupant": "titulaire",
    "secretary": "suppléant"
};

function format_up1Roles(info) {
    var mail2roles = {};
    var mail2seeAlso = {}
    $.each(info.up1Roles, function (i, e) {
	if (!mail2roles[e.mail]) mail2roles[e.mail] = []
	mail2roles[e.mail].push(role2text[e.role] || e.role);
	mail2seeAlso[e.mail] = e.seeAlso;
    });
    return spanFromList($.map(mail2roles, function (roles, mail) {
	return $("<span>", { title: mail2seeAlso[mail] })
	    .append(format_mail(mail))
	    .appendText(" (" + roles.join(", ") + ")");
    }), "<br>");
}

function format_supannCodeEntite(l, showExtendedInfo) {
    return spanFromList(rejectEmpty($.map(l, function (e) {
	if (e.name) {
	    var title = e.description + (showExtendedInfo ? " (" + e.key + ")" : "");
	    return a_or_span(e.labeledURI, e.name).attr('title', title);
	} else
	    return null;
    })), ", ");
}
function format_supannEntiteAffectation(info, showExtendedInfo) {
	return format_supannCodeEntite(info['supannEntiteAffectation-all'], showExtendedInfo);
}
function format_supannParrainDN(info, showExtendedInfo) {
    return format_supannCodeEntite(info['supannParrainDN-all'], showExtendedInfo);
}

function format_mail(mail, displayName) {
    var dest = displayName ? displayName + " <" + mail + ">" : mail;
    return $("<a>", { href: 'mailto:' + encodeURIComponent(dest) }).text(mail);
}

function format_telephoneNumber(number, attr, showExtendedInfo) {
    var linkName = attr == 'facsimileTelephoneNumber' ? 'fax' : 'tel';
    var format_it = function (number) {
	var number_ = number.replace(/^0([67])(\d\d)(\d\d)(\d\d)(\d\d)$/, '+33 $1 $2 $3 $4 $5');
	var link = number_.replace(/[^0-9+]/g, '');
	var a = $("<a>", { href: linkName + ':' + link }).text(number_);
	if (showExtendedInfo) a.attr('title', attr + ": " + number);
	return a;
    };
    if ($.isArray(number)) {
	return spanFromList($.map(number, format_it), ", ");
    } else {
	return format_it(number);
    }
}

function format_buildingName(buildingNames) {
    return spanFromList($.map(buildingNames, function (buildingName) {
	return a_or_span(buildingNameToUrl(buildingName), buildingName);
    }), ", ");
}

function a_or_span(href, text) {
    var node = href ? $("<a>", { href: encodeURI(href) }) : $("<span>");
    return node.text(text);
}

function format_link(link) {
    return a_or_span(link, link);
}

function formatUserInfo(info, showExtendedInfo) {
    //if (info.roomNumber) info.postalAddress = info.roomNumber + ", " + info.postalAddress;
    if (!showExtendedInfo) {
	delete info.up1Roles; // TODO, handle it in web-service?
	delete info.supannParrainDN;
	delete info.memberOf; delete info['memberOf-all'];
    }
    if (showExtendedInfo) info.Identifiers = compute_Identifiers(info);

    if (info.mailForwardingAddress) info.MailDelivery = compute_MailDelivery(info);

    var fInfo = {};

    formatSomeUserValues(info, fInfo);

    fInfo.Person = compute_Person(info, showExtendedInfo);
    if (info.supannListeRouge) fInfo.supannListeRouge = info.supannListeRouge === "TRUE" && important("oui");
    if (info.shadowExpire) fInfo.shadowExpire = format_shadowExpire(info);   
    if (info.createTimestamp) fInfo.createTimestamp = format_timestamp(info.createTimestamp);   
    if (info.modifyTimestamp) fInfo.modifyTimestamp = format_timestamp(info.modifyTimestamp);   
    if (info.eduPersonPrimaryAffiliation || info.eduPersonAffiliation) fInfo.Affiliation = compute_Affiliation(info, showExtendedInfo);
    if (info['supannEtuInscription-all']) format_supannEtuInscriptionAll(info['supannEtuInscription-all'], fInfo, showExtendedInfo);
    if (info['supannActivite-all']) format_supannActivite(info['supannActivite-all'], fInfo, showExtendedInfo);
    if (showExtendedInfo) {
	// if we have up1BirthDay, we have full power
	compute_Account_and_accountStatus(info, fInfo);
    }
    fInfo.Fonctions = compute_Fonctions(info);

    if (info.up1Roles) fInfo.up1Roles = format_up1Roles(info);
    if (info['memberOf-all']) fInfo.memberOf = format_memberOf(info['memberOf-all']);
    if (info.supannParrainDN) fInfo['supannParrainDN-all'] = format_supannParrainDN(info, showExtendedInfo);
    if (info.supannEntiteAffectation) fInfo['supannEntiteAffectation-all'] = format_supannEntiteAffectation(info, showExtendedInfo);
    if (info.buildingName) fInfo.buildingName = format_buildingName(info.buildingName);
    if (info.labeledURI) fInfo.labeledURI = format_link(info.labeledURI);
    $.each(['telephoneNumber', 'facsimileTelephoneNumber', 'supannAutreTelephone', 'mobile', 'pager'], function (i, attr) {
	if (info[attr]) fInfo[attr] = format_telephoneNumber(info[attr], attr, showExtendedInfo);
    });
    $.each(['mail', 'mailAlternateAddress', 'supannAutreMail', 'supannMailPerso'], function (i, attr) {
	if (info[attr]) fInfo[attr] = format_mail(info[attr], info.displayName);
    });

       var div = $("<div></div>");
       $.each(main_attrs_labels, function (i, sections) {
	   var attrs_labels = parse_attrs_text(sections);
	   var table = $("<table style='border: 1px solid; padding: 1em; margin: 0.5em; 0'></table>").appendTo(div);
	   $.each(attrs_labels, function (i, e) {
	       var fv = fInfo[e.attr];
	       var v = !(e.attr in fInfo) && info[e.attr];
	       if (!fv && !v) return;

	       var td_v = $("<td></td>");
	       if (fv)
		   td_v.append(fv);
	       else if (typeof v === "string") 
		   appendWrappedText(td_v, v);
	       else if ($.isArray(v))
		   appendWrappedText(td_v, v.join(', '));
	       else
		   throw "InternalError";
	       
	       $("<tr></tr>")
		   .append($("<td></td>").html(e.text))
		   .append(td_v)
		   .appendTo(table);
	   });
       });
    return div;
}

   var infoDiv = $("<div class='annuaireResult'>");

   function asyncInfo(user) {
       currentUser = user;
       infoDiv.text("Vous avez selectionné " + user.label + ". Veuillez patienter...");
       // to be able to bookmark users
       window.location.hash = '#' + user.value;

       infoDiv.toggleClass('showExtendedInfo', showExtendedInfo);
       
       var wsParams = {
	   token: user.value,
	   showErrors: showExtendedInfo,
	   allowInvalidAccounts: true,
	   showExtendedInfo: showExtendedInfo
       };
       $.ajax({
	   url: searchUserURL,
	   dataType: "jsonp",
	   crossDomain: true, // needed if searchUserURL is CAS-ified or on a different host than application using autocompleteUser
	   data: wsParams,
	   error: function () {
	       infoDiv.text("Erreur web service");
	   },
	   success: function (data) {
	       if (data.length == 0) {
		   infoDiv.text("user not found (??)");
	       } else if (data.length > 1) {
		   infoDiv.text("internal error (multiple user found)");
	       } else {
		   infoDiv.empty().append(formatUserInfo(data[0], showExtendedInfo));
	       }
	   }
       });
   }

   var select = function (event, ui) {
        $(this).blur(); // important to close virtual keyboard on mobile phones
        asyncInfo(ui.item);
        return false;
    };

   var install_autocompleteUser = function (showExtendedInfo) {
       var input = $("#all");
       input.autocompleteUser(searchUserURL, { 
	   select: select, disableEnterKey: true, 
	   wantedAttr: 'mail', // mail is best attr to do a further searchUser to get all attrs
	   wsParams: { showErrors: showExtendedInfo, allowInvalidAccounts: showExtendedInfo, showExtendedInfo: showExtendedInfo } } );
       input.attr('placeholder', 'Nom prénom');
       input.handlePlaceholderOnIE();
   };

    function searchForm() {
	return $("<form method='get' action='/foo' class='welcomeAutocompleteSearch'></form>")
	    .append($("<div class='ui-widget'></div>")
		    .append($("<label for='all'>Saisissez le nom et/ou prénom d'un étudiant ou d'un personnel</label>"))
		    .append($("<span class='token-autocomplete'></span>")
			    .append($("<input id='all' name='all' placeholder='Nom prénom' />")))
		    .append($("<label class='checkbox'>")
			    .append($("<input type='checkbox' id='showExtendedInfo' name='showExtendedInfo'>"))
			    .append("Informations détaillées"))
		    );
    }

    function useHashParam() {
	var value = document.location.hash && document.location.hash.replace(/^#/, '');
	if (value && (!currentUser || currentUser.value != value)) {
	    asyncInfo({ label: value, value: value });
	}
    }

    function init() {
	$("#annuaire").append(searchForm()).append(infoDiv);

	install_autocompleteUser(showExtendedInfo);

	$("#showExtendedInfo").change(function () {
	    showExtendedInfo = $(this).attr('checked');
	    install_autocompleteUser(showExtendedInfo);
	    asyncInfo(currentUser);
	});
	$("#showExtendedInfo").attr('checked', showExtendedInfo ? 'checked' : false);

	$(window).on('hashchange', useHashParam);
    }

    init();
    useHashParam();

    infoDiv.on('click', "span[title]", function () {
	var $title = $(this).find(".clickedTitle");
	if (!$title.length) {
	    $(this).append($('<span>', { 'class': "clickedTitle" }).text($(this).attr("title")));
	} else {
	    $title.remove();
	}
    });

})(jQuery);
