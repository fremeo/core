var wp = wp || {};

wp.ajax = function (D) {
    if (!isNaN(document.getElementById(D.div)))
        $("body").append("<div id='" + D.div + "' style='display:none;'></div>");

	if(D.INSERT == 'replace' || typeof D.INSERT == 'undefined')
		$("#" + D.div).html('<style>.loader { border: 3px solid #f3f3f3;border-top: 3px solid #3498db;border-radius: 50%;width: 50px;height: 50px;animation: spin 2s linear infinite;position: absolute; left: 50%;top: 50%;  }@keyframes spin {0% { transform: rotate(0deg); }100% { transform: rotate(360deg); }}</style><div style="height:100%;width:100%;background: #222222;"><div><div class="loader"></div></div>');
   $.ajax({
        type: (D.method) ? D.method : 'POST',
        url: D.url+(((D.url).indexOf('?') > -1)?'&_=':'?_=')+Date.now(),
        data: D.data,
        cache: false,
        async: true //((!isNaN(D.ASYNC))?D.ASYNC:false)//ToDo: Beobachten!
        //contentType: "application/json; charset=utf-8"
        //dataType: "json"
    })
	.done(function (html) {
	    //document.getElementById(D.div).innerHTML = html;
	    //$(D.div).html(html);
	    switch (D.INSERT) {
	        case 'append': //Anhängen am ende
	            $("#" + D.div).append(html);
	            break;
	        case 'prepend': //Anhängen am Anfang
	            $("#" + D.div).prepend(html);
	            break;
	        case 'replace': //Ersetzen
	        default: //Neu Laden
	            $("#" + D.div).html(html);
	            break;
	    }

        /*
	    $("#" + D.div).find("script").each(function (i) {
	       // eval($(this).text());
	    });*/
	    if (typeof D.done != 'undefined')
	        D.done();
	});
}


wp.get_genID = function () {
uuid = '';
    $.ajax({
        async: false,
        url: "get_uuid.php",
        success: function(data) {
            uuid = data;
        }});
return uuid;
    /*
    d = new Date();
    n = d.getTime();

    random = parseInt(Math.random() * ((9 - 0) + 0));
    abc = 'abcdefghij';
    return abc[random] + n.toString(36); //in PHP $hex = base_convert(4353454654, 10, 36);
    */
}

//===Maus Position berechnen==============
wp.mouse = wp.mouse || {};
$(document).bind('mousemove', function (e) {
   wp.mouse.x =  e.pageX;
   wp.mouse.y =  e.pageY;
}); 

//===WINDOW=========================
wp.window = wp.window || {};
zindex = 2000;
wp.window.open = function (D) {
    if (!isNaN(document.getElementById("wp" + D.ID))) {
        html = "<div id='wp" + D.ID + "' style='z-index:"+(zindex++)+";width:" + (typeof D.WIDTH != 'undefined' ? D.WIDTH : "") + ";position:fixed;background:#f3f3f3; border:solid 1px #999;top:100px;left:100px;border-radius:5px;'>"
        + " <div id='wp" + D.ID + "DestinationContainerNode' style='cursor:pointer;padding:2px 4px;'><div style='float:left;padding-top:4px;margin-right:4px;'>" + (typeof D.TITLE != 'undefined' ? D.TITLE : "wp" + D.ID) + "</div>"
        + "     <div style='float:right;'><i class='fa fa-refresh' style='font-size:12pt;color:green;'></i> <i class='fa fa-window-minimize' onclick=\"wp.minimize('" + D.ID + "');\" style='font-size:12pt;color:green;'></i> <!--<button type='button' class='minclose'>-</button>--><i onclick=\"wp.window.close('" + D.ID + "');\" style='font-size:13pt;color:red;' class='fa fa-times'></i></div>"
        + "         <div style='clear:both;width:250px;'></div></div>"
        + "     <div>"
        + "     </div>"
        + "<div id='wp"+D.ID+"_bar'></div>"

        + "        <div id='wp" + D.ID + "body' class='body' style='margin:0 4px;'>"

        + "             <div style='border: 1px solid #999;'>";
/*
        + "<div class='navibar'>"
		+ " <ul class='h'>"
        + "     <li><div>SSS</div></li>"
        + "</ul>"
        + "</div>"
*/


        html += "                 <div id='wp" + D.ID + "body_load' style='/*width:" + (typeof D.WIDTH != 'undefined' ? D.WIDTH : "") + ";*/height:" + (typeof D.HEIGHT != 'undefined' ? D.HEIGHT : "200px") + ";background:#fff;overflow:auto;'></div>";
        //html += "                 <div id='wp" + D.ID + "body_load'><iframe id='wp" + D.ID + "body_load1' style='border:none;width:100%;height:100%;background:#fff;overflow:auto;' src=\'index.php\'></iframe></div>";
        
        html += "             </div>"
        
        + "             <div style='text-align:right;padding:2px 20px;'>";

		html += "<div id='wp"+D.ID+"footer' class='btn-group'>";
        //html += "                 <button type='button' class='btn btn-default btn-save' onclick=\"SAVE" + D.ID.replace('-', '_').replace('-', '_') + "();wp.window.close('" + D.ID + "');\">OK</button>";
        
        html += "                 <button type='button' class='btn btn-default btn-save'>OK</button>";
        html += "                 <button type='button' class='btn btn-default' onclick=\"wp.window.close('" + D.ID + "');\">Abbrechen</button>";
        //html += "                 <button type='button' class='btn btn-default' onclick=\"SAVE" + D.ID.replace('-', '_').replace('-', '_') + "();\">Übernehmen</button>";
        html += "                 <button type='button' class='btn btn-default btn-accept'>Übernehmen</button>";
		html += "</div>"
        + "             </div>"
        + "        </div>"
        + " </div>";
        $("body").append(html);

        //in layer_footer einfügen Tab
        tab = "<div id='wp_tab" + D.ID + "' onclick=\"wp.minimize('" + D.ID + "');\" style='background:#fff;float:left;margin:2px;border:solid 2px #ddd;padding:0 0 0 4px;border-radius:5px;cursor:pointer;'>"
        + "<div style='float:left;line-height:22px;margin-right:4px;'>" + (typeof D.TITLE != 'undefined' ? D.TITLE : "wp" + D.ID) + "</div>"
        + "<div style='float:right;'><button type='button' class='btn btn-xs' onclick=\"wp.window.close('" + D.ID + "');\"><i class='fa fa-times' style='color:red;'></i></button></div>"
        + "</div>";
        $('#layer_footer').append(tab);



/*
        tab = "<li id='wp_tab" + D.ID + "' onclick=\"wp.minimize('" + D.ID + "');\" class='nav-item' role='presentation'><a class='nav-link' data-toggle='tab'>" + (typeof D.TITLE != 'undefined' ? D.TITLE : "wp" + D.ID) + "<button type='button' class='btn btn-xs' onclick=\"wp.window.close('" + D.ID + "');\"><i class='fa fa-times' style='color:red;'></i></button></a></li>";
        $('#nav_page').append(tab);
*/
        //OK & Übernehmen Start============
        //OK
         
        $("#wp" + D.ID + " .btn-save").unbind("click").bind("click", function () {
            btnBg = $(this).css('background-color');
            $(this).css('background-color','red');
            $('#wp' + D.ID + 'body_load form').find(':input').each(function(){
                if( $(this).attr( "ischanged" ) == 0 )
                    $(this).attr( "disabled", true );
            });
            
            wp.ajax({
            //'url' : '?D[PAGE]=platform.article&D[ACTION]=set_article&D[PLATFORM_ID]={$D.PLATFORM_ID}&D[ACCOUNT_ID]={$D.SESSION.ACCOUNT_ID}',
            'url' : D.URL+'&D[ACTION]=save',
            'div' : 'ajax',
            'data': $('#wp' + D.ID + 'body_load form').serialize(),
            'done': function(){$('#wp' + D.ID + ' #wp'+D.ID+'footer .btn-save').css('background-color',btnBg);wp.window.close(D.ID);}
            });

            $('#wp' + D.ID + 'body_load form').find(':input').each(function(){
                $(this).attr( "disabled", false );
            });
           // wp.window.close(D.ID);
        });

        //Übernehmen
        $("#wp" + D.ID + " .btn-accept").unbind("click").bind("click", function () {
            btnBg = $(this).css('background-color');
            $(this).css('background-color','red');
            $('#wp' + D.ID + 'body_load form').find(':input').each(function(){
                if( $(this).attr( "ischanged" ) == 0 )
                    $(this).attr( "disabled", true );
                //else
                //    $(this).removeAttr("ischanged");//entferne das Attribut, damit das inputt nach speichern weiterhin immer gespeichert wird, trotz der rücksetzung
            });
            
            wp.ajax({
            //'url' : '?D[PAGE]=platform.article&D[ACTION]=set_article&D[PLATFORM_ID]={$D.PLATFORM_ID}&D[ACCOUNT_ID]={$D.SESSION.ACCOUNT_ID}',
            'url' : D.URL+'&D[ACTION]=save',
            'div' : 'ajax',
            'data': $('#wp' + D.ID + 'body_load form').serialize(),
            'done': function(){$('#wp' + D.ID + ' #wp'+D.ID+'footer .btn-accept').css('background-color',btnBg)}
            });

            $('#wp' + D.ID + 'body_load form').find(':input').each(function(){
                $(this).attr( "disabled", false );
            });
        });

        
        // Ende ===========================

        //refresh
        $("#wp" + D.ID + " .fa-refresh").unbind("click").bind("click", function () {
            wp.ajax({ 'div': "wp" + D.ID + "body_load", 'url': D.URL, 'data': D.DATA, 'ASYNC' : 1});
        });

        // $("#" + id).hide('explode', {}, 500);
       
        $("#wp" + D.ID).unbind("mousedown").bind("mousedown", function () {
            zindex++;
            $(this).css('z-index', zindex); //Vordergrund
        });

        //minclose
        $("#wp" + D.ID + " .minclose").unbind("click").bind("click", function () {
            $("#wp" + D.ID + " .body").toggle("blind");
        });
        //Minimieren
        /*
        $("#wp" + D.ID + " .minimize").unbind("click").bind("click", function () {
            $("#wp" + D.ID).toggle("clip", function () {
                document.getElementById("wp" + D.ID).style.display = 'none';
            });
        });
        */

        //Verschieben
        // $("#wp" + D.ID).disableSelection().draggable({ 'handle': "#wp" + D.ID + "DestinationContainerNode" });
        $("#wp" + D.ID).draggable({ 'handle': "#wp" + D.ID + "DestinationContainerNode" });


        $("#wp" + D.ID).resizable({
            alsoResize: "#wp" + D.ID ,
            minHeight: 100,
            minWidth: 260,
            resize: function(event, ui) { 
                $("#wp" + D.ID +'body_load').height( 
                    $("#wp" + D.ID).height()
                    -$('#wp'+D.ID+'footer').outerHeight()
                    -$('#wp' + D.ID + 'DestinationContainerNode').outerHeight()
                    -(($('#wp'+D.ID+'_bar').outerHeight() > 0 )? $('#wp'+D.ID+'_bar').outerHeight()+8:0)
                    -6 
                );
                //alert($('#wp'+D.ID+'footer').outerHeight()+'|'+$('#wp' + D.ID + 'DestinationContainerNode').outerHeight()+'|'+$('#wp'+D.ID+'_bar').outerHeight()+'|'+$('#wp'+D.ID+'_bar').innerHeight());
            }
        });
        
        wp.ajax({ 'div': "wp" + D.ID + "body_load", 'url': D.URL, 'data': D.DATA, 'ASYNC' : 1  });
        //Zentriere Fenster
        $('#wp' + D.ID).css("top", (($(window).height() - $('#wp' + D.ID).outerHeight()) / 2) + "px");
        $('#wp' + D.ID).css("left", (($(window).width() - $('#wp' + D.ID).outerWidth()) / 2)  + "px");
    
    
    
            //Mobile Drag & Drop
            var boxTop = document.getElementById("wp" + D.ID + "DestinationContainerNode");
            var box = document.getElementById("wp" + D.ID + "");
            var xStart = parseInt(box.style.left);
            var yStart = parseInt(box.style.top);
            var xTouchStart = 0;
            var yTouchStart = 0;
            boxTop.addEventListener('touchstart', function(e) {
                boxTop.style.background = "#ffb1b1";
            });
            boxTop.addEventListener('touchmove', function(e) {
                // grab the location of touch
                e.preventDefault();
                // assign box new coordinates based on the touch.
                if(xTouchStart == 0) {
                    xTouchStart = e.touches[0].pageX-xStart;
                    yTouchStart = e.touches[0].pageY-yStart;
                }

                box.style.left = (e.touches[0].pageX-xTouchStart) + 'px';
                box.style.top = (e.touches[0].pageY-yTouchStart) + 'px';
              });
              boxTop.addEventListener('touchend', function(e) {
                // current box position.
                xStart = parseInt(box.style.left);
                yStart = parseInt(box.style.top);
                xTouchStart = 0; //reset
                yTouchStart = 0; //reset
                boxTop.style.background = "";
              });


    }
    else
        wp.minimize(D.ID);
        //$("#wp" + D.ID).toggle("clip");
}

wp.window.close = function (id) {

/*
    $("#wp" + id).hide('scale', {}, 500, function () 
    {
     $("#wp" + id).remove();
    });
    */
    
    $("#wp" + id).remove();
    $("#wp_tab" + id).remove();
}

wp.window.bar = function(id,D) {
    if(D.Breadcrumb)
        $('#wp'+id+'_bar').html('<div style="padding:2px 4px;margin:4px;border:solid 1px #999;background:#fff;">'+D.Breadcrumb+'</div>');
}

wp.minimize = function (id) {
    if (document.getElementById("wp" + id).style.display == 'none') {
        document.getElementById("wp" + id).style.display = '';
        document.getElementById("wp_tab" + id).style.backgroundColor = '#fff';
    }
    else {
        document.getElementById("wp" + id).style.display = 'none';
        document.getElementById("wp_tab" + id).style.backgroundColor = '#ddd';
    }
}

wp.contextmenu = wp.contextmenu || {};
wp.contextmenu.open = function (D) {
    if (!isNaN(document.getElementById('context')))
        $("body").append("<div id='context' class='contextmenu'</div>");

    m = "<ul id='context" + D.ID + "'>";
    for (var i = 0; i < D.MENU.length; i++)
        m += "<li class='" + D.MENU[i].CLASS + "' onclick=\"" + D.MENU[i].ONCLICK + ";\">" + D.MENU[i].TITLE + "</li>";

    m += "</ul>";
   
    $("#context").html(m);
    document.getElementById("context").style.display = '';
    document.getElementById("context").style.top = wp.mouse.y+'px';
    document.getElementById("context").style.left = wp.mouse.x+'px';
    return false;
}

wp.contextmenu.close = function () {
    if (isNaN(document.getElementById('context')))
        document.getElementById("context").style.display = 'none';
}

wp.tab = wp.tab || {};
wp.tab.open = function (D) {
    
    if (!isNaN(document.getElementById("wp_" + D.ID))) {
        html = '<li id="wp_nav_'+D.ID+'" class="nav-item" role="presentation"><a class="nav-link" id="wp_'+D.ID+'" href="#wpTabContainer'+D.ID+'" data-toggle="tab">'+D.TITLE
        +"<i onclick=\"wp.tab.close('" + D.ID + "');\" class='fa fa-times pl-1' style='color:red;'></i>"
        +'</a></li>';
        $("#nav_page").append(html);

        html = '<div class="tab-pane fade" id="wpTabContainer'+D.ID+'" role="tabpanel" aria-labelledby="profile-tab">'
        + '<div id="wpContent'+D.ID+'"></div>'
       // + '     <div class="" style="position:fixed;background:#eee;height:20px;width:100%;bottom:5px;">'
       // + "           <button type='button' class='btn btn-default btn-accept'>Übernehmen</button>";
       // + '     </div>'
        + '</div>';
        $("#nav_pageContent").append(html);

        wp.ajax({ 'div': 'wpContent'+D.ID, 'url': D.URL, 'data': D.DATA, 'ASYNC' : 1});
    }
 
    $('#wp_'+D.ID).trigger('click'); //Aktiviere Tab
}

wp.tab.close = function (id) {
    $("#wp_nav_" + id).remove();
    $("#wpTabContainer" + id).remove();
}


//vergrößert Textarea automatisch Verwenden wir Jquery tüpisch $('textarea.input').elasticArea();
jQuery.fn.elasticArea = function () {
    return this.each(function () {
        function resizeTextarea() {
            this.style.height = this.scrollHeight / 2 + 'px';
            this.style.height = this.scrollHeight + 'px';
        }

        $(this).keypress(resizeTextarea).keydown(resizeTextarea).keyup(resizeTextarea).css('overflow', 'hidden');

        resizeTextarea.call(this);
    });
};

//d.sortRow = Nummer welche Reihe zu sortieren
//d.table = table jquery like '#mytable'
wp.sortTable = function(d) {
    var rows = $(d.table+' tbody tr').get();
  
    rows.sort(function(a, b) {
  
    var A = $(a).children('td').eq(d.sortRow).text().toUpperCase();
    var B = $(b).children('td').eq(d.sortRow).text().toUpperCase();
  
    if(A < B) {
      return -1;
    }
  
    if(A > B) {
      return 1;
    }
  
    return 0;
  
    });
  
    $.each(rows, function(index, row) {
      $(d.table).children('tbody').append(row);
    });
  }