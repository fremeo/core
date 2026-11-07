// Simple JavaScript Templating
// John Resig - https://johnresig.com/ - MIT Licensed
//(function(){
    var cache = { };
	var tmpl = tmpl || {};
  tmpl.fetch = function tmpl(str, data) {
    // Figure out if we're getting a template, or if we need to
    // load the template - and be sure to cache the result.
    var fn = !/\W/.test(str) ?
      cache[str] = cache[str] ||
        tmpl(document.getElementById(str).innerHTML) :
       
      // Generate a reusable function that will serve as a template
      // generator (and which will be cached).
      new Function("obj",
        "var p=[],print=function(){ p.push.apply(p,arguments);};" +
         
        // Introduce the data as local variables using with(){}
        "with(obj){ p.push('" +
         
        // Convert the template into pure JavaScript
		str.replace(/[\r\t\n]/g, " ")
		.replace(/'(?=[^%]*%>)/g,"\t")
		.split("'").join("\\'")
		.split("\t").join("'")
		.replace(/<%=(.+?)%>/g, "',$1,'")
		.split("<%").join("');")
		.split("%>").join("p.push('")
		+ "');}return p.join('');");
     
    // Provide some basic currying to the user
    return data ? fn( data ) : fn;
  };
//})();
	//['type' : 'text,..', value : string,  ]
	tmpl.input = function (d) {
		switch(d.type) {
			case 'text':
			case 'password':
			case 'hidden':
				return "<input "
				+ (d.type !== undefined ? "type='"+d.type+"' " : '')
				+ (d.class !== undefined ? "class='"+d.class+"' " : "class='form-control' ")
				+ (d.name !== undefined ? "name='"+d.name+"' " : '')
				+ (d.id !== undefined ? "id='"+d.id+"' " : '')
				+ (d.value !== undefined && d.value !== null ? "value='"+d.value+"' " : '')
				+ (d.readonly !== undefined ? "readonly='readonly' " : '')
				+ (d.onclick !== undefined ? 'onclick="'+d.onclick+'" ' : '')
				+ ">";
			 break;
		}
	};

	