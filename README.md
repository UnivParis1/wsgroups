wsgroups est un web-service pour rechercher des supannPerson et des groupes dans LDAP.

autocompleteUser.js est un plugin jquery permettant de transformer des simples &lt;input&gt; en web-widget.

# Web widget de recherche d'une personne

Voici une capture d'écran :

![](http://wsgroups.univ-paris1.fr/doc/test-search-supannPerson.png)

## Exemple complet : recherche d'une personne, retourne l'uid

```html
  <script src="https://wsgroups.univ-paris1.fr/web-widget/autocompleteUser-resources.html.js"></script>

  <input id="person" name="person" placeholder="Nom et/ou prenom" />

  <script>
   $( "#person" ).autocompleteUser(
      'https://wsgroups.univ-paris1.fr/searchUser', {}
   );
  </script>
```

## Exemple : recherche d'une personne, retourne l'adresse mail

(ajouter les balises &lt;link&gt; et &lt;script&gt; du premier exemple)

```html
  <input id="email_search" name="email" type="text" size="35" />

  <script>
   $( "#email_search" ).autocompleteUser(
      'https://wsgroups.univ-paris1.fr/searchUser', { wantedAttr: "mail" }
    );
  </script>
```

## Filtrer les résultats

```javascript
   $( "#student" ).autocompleteUser(url, { wsParams: { filter_eduPersonAffiliation: "student" } });
   $( "#other" ).autocompleteUser(url, { wsParams: { filter_not_eduPersonAffiliation: "student|alum" } });
```

## Liste rouge

Si une personne sur liste rouge correspond au critère de recherche, le widget affichera le message :

```
NB : un résultat a été caché
à la demande de la personne.
```

Pour ne pas avoir cette limitation, il faut utiliser le web-service "searchUserCAS". Exemple :

```javascript
   $( "#person" ).autocompleteUser(
      'https://wsgroups.univ-paris1.fr/searchUserCAS', {}
   );
```

NB : pour que cela fonctionne, il faut que la page qui utilise le web-widget soit elle-même CAS-ifiée.


## Exemple complexe : input caché et intégration dans une application JSF 1

```html
  <tr:form defaultCommand="submitButton">
            
     <tr:inputText
             styleClass="token-autocomplete"
             shortDesc="Rechercher une personne en donnant une partie de son nom-prénom"
             value="#{uidController.token}" />
  
     <tr:outputFormatted styleUsage="instruction"
           styleClass="onsubmit-msg"
           value="" />
  
     <tr:inputText
             styleClass="uid-autocomplete" 
             inlineStyle="display: none"
             value="#{uidController.uid}" />
                     
     <tr:commandButton id="submitButton" 
             inlineStyle="display: none"
             action="#{uidController.searchAction}"/>
  </tr:form>

  <script>

  (function () {
    var select = function (event, ui) {
        // NB: this event is called before the selected value is set in the "input"

        var form = $(this).closest("form");

        form.find(".uid-autocomplete input").val(ui.item.value); 
        
        form.find(".onsubmit-msg").text("Vous avez selectionné " + ui.item.label + ". Veuillez patienter...");
        form.find("button").click();

        return false;
    };

    $( ".token-autocomplete input" ).autocompleteUser(
      '#{uidController.wsgroupsUrl}/#{uidController.authenticated ? "searchUserCAS" : "searchUser"}', { 
         select: select
      }
    );

    $( ".token-autocomplete input" ).attr('placeholder', 'Nom prénom');
    $( ".token-autocomplete input" ).handlePlaceholderOnIE();

  })();

  </script>
```

# Web service


## group keys

* `affiliation-xxx` (where xxx is enumerated in `$AFFILIATION2TEXT`)
* `businessCategory-xxx` (where xxx is enumerated in `$BUSINESSCATEGORY2TEXT`)
* `structures-xxx` (where xxx is a `supannCodeEntite` in `ou=structures`)
* `structures-xxx-affiliation-yyy` (same as above with limitations on an affiliation)
* `diploma-xxx` (where xxx is a `ou` in `ou=2016,ou=diploma,o=Paris1`)
* `groups-xxx` (where xxx is a `cn` in `ou=groups`)

The first part of a group key is the *category*.

### examples

* `structures-U02-affiliation-student` (students of UFR02)
* `structures-DGHE-affiliation-student` (students of DSIUN-SUN)
* `structures-DGHE-affiliation-staff` (staff of DSIUN-SUN)
* `structures-DGHE-affiliation-employee` (staff of DSIUN-SUN)
* `groups-employees.administration.DGH` (employees of DSIUN*, group created in grouper)
* `diploma-L2B101` (Licence 1 1ere année Economie)


## /searchUser

### params

* `token=`text
* `maxRows=`number
* `attrs=`attr,attr2,...
* `callback=`function name for jsonp
* `filter_eduPersonAffiliation=`affiliation|...
* `filter_eduPersonPrimaryAffiliation=`affiliation|...
* `filter_supannEntiteAffectation=`affectation|...
* `filter_supannRoleGenerique=`role id|...
* `filter_member_of_group=`group|...
* `filter_description=`text|... (exact search)
* `filter_not_eduPersonAffiliation=`affiliation|...
* `filter_not_supannEntiteAffectation=`affectation|...
* `filter_not_member_of_group=`group|...
* `filter_not_description=`text|... (exact search)
* `filter_mail=`*
* `filter_student=no` (equivalent to filter_not_supannEntiteAffectation=student)
* `filter_student=only` (equivalent to filter_supannEntiteAffectation=student)

params allowed if casified user member of `$LEVEL1_FILTER` & `$LEVEL2_FILTER`:
* `showExtendedInfo=true`
* `showErrors=true`
* `allowInvalidAccounts=true`

### known usages:

* web-widget /searchUserCAS
  * `filter_eduPersonAffiliation=employee`
  * `filter_eduPersonAffiliation=staff`
  * `filter_eduPersonAffiliation=student`
  * `filter_not_eduPersonAffiliation=student`
* bisintra /searchUserTrusted
  * attrs=uid,displayName,supannEntiteAffectation,telephoneNumber,mail,buildingName,roomNumber,up1FloorNumber,description,info
  * filter_member_of_group=structures-IU2|structures-IU21|structures-IU23|...
* web-widget /searchUser
  * filter_member_of_group=structures-IU2|structures-IU21|structures-IU23|...
  * filter_eduPersonAffiliation=staff



## /searchGroup

### params

* `token=`text
* `maxRows=`number
* `attrs=`attr,attr2,... (valid attrs: `businessCategory`)
* `callback=`function name for jsonp
* `group_filter_attrs=`attr,attr2 (by default, search on diploma is done on `ou` and `description`)
* `filter_category=`category|...

### known usages:

* autocompleteGroup web-widget (used in smsu)

  
## /getGroup

### params

* `key=`group
* `attrs=`attr,... (many attrs by default, optional attrs: `roles`)

### result example

```javascript
{
    "rawKey": "DGE"
    "key": "structures-DGE",
    "name": "DRH : Direction des ressources humaines",
    "description": "",
    "businessCategory": "administration",
    "labeledURI": "http://www.univ-paris1.fr/universite/xxx",
    "modifyTimestamp": "20160316184014Z",
    "roles": [
        {
            "uid": "prigaux",
            "displayName": "Pascal Rigaux",
            "supannRoleGenerique": [ "Chef de service", "Directeur(ice)" ]
        }
    ]
}
```  

### usages
* smsu
* userinfo (with attrs=roles)


## /groupUsers

### params
* client IP (unlimited results if $TRUSTED_IPS, otherwise only 5)
* `key=`group
* `attr=`attr (optional)

### result example

with `attr=mail`:

```javascript
  [
    "Pascal.Rigaux@univ-paris1.fr",
    ...
  ]
```

### usages
* bisintra key=structures-IU2C
* bisintra key=structures-IU2x
* sifac key=groups-applications.sifac.users
* smsu


## /userGroupsId

### params
* `uid=`xxx

### result example

```javascript
[
    {
      "key": "affiliation-staff",
      "name": "Tous les Biatss",
      "description": "Tous les Biatss",
    },
    ...
]
```

### usages
* smsu 


## /userGroupsAndRoles

### params
* `uid=`xxx

### result example

```javascript
[
    {
      "key": "affiliation-staff",
      "name": "Tous les Biatss",
      "description": "Tous les Biatss",
      "role": ""
    },
    {
      "key": "groups-applications.horde5.users",
      "name": "Utilisateurs Horde5",
      "role": "Responsable"
    },
    ...
]
```

### usages
* moodle


## /allGroups

### params
* `key=`group

### result example

```javascript
[
  {"key":"businessCategory-pedagogy","name":"Composantes personnels","description":"Composantes personnels"},
  ...
]
```

### usages
* moodle


## /getSubGroups

### params
* `key=`group
* `depth=`number (default: 1)
* `filter_category=`category|...

### result example

```javascript
[
    {
        "key": "diploma-xxx",
        "name": "xxx - Zzz",
        "description": "xxx - Zzz",
        "category": "diploma"
        "subGroups": [
          ...
        ]
    },
    ...
]
```

### usages
* moodle
* bisintra key=structures-iu2&depth=3
* bisintra key=structures-iu2&depth=3&token=xxxx
  

## /getSuperGroups

### params
* `key=`group
* `depth=`number (default: 1)
* `filter_category=`category|...

### result example

```javascript
  {
    "diploma-xxx": {
        "key": "diploma-xxx",
        "rawKey": "xxx",
        "name": "xxx - Zzz",
        "description": "xxx - Zzz",
        "modifyTimestamp": "20160618040149Z",
        "category": "diploma",
        "superGroups": [
            "structures-U02-affiliation-student"
        ]
    },
    "structures-U02-affiliation-student": {
      "key": "structures-U02-affiliation-student",
      "name": "UFR 02 : Economie (étudiants)",
      "description": "",
      "category": "structures",
      "superGroups": [
          "affiliation-student"
      ]
    },
    "affiliation-student": {
      "key": "affiliation-student",
      "name": "Tous les étudiants",
      "description": "Tous les étudiants",
      "category": "affiliation",
      "superGroups": [ ]
    }
  }
```

### usages
* moodle
* smsu

  
## /getSubAndSuperGroups

### params
* `key=`group
* `depth=`number (default: 1)


## /userLastLogins

### params
* casified, user must be member of `$LEVEL2_FILTER`
* `login=`xxx
  
### usages
* userinfo


## /userMoreInfo

### params
* casified, user must be member of `$LEVEL1_FILTER` or `$LEVEL2_FILTER`
* `uid=`xxx
* `info=`xxx (optional, values: `auth`, `mailbox`, `folder`)

### usages
* userinfo
