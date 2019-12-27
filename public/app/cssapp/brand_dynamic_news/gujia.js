window.onload = function(){
	var vu = document.getElementsByClassName('vu')
	for(var i = 0;i<vu.length;i++) {
		var son = vu[i];
		setson(son);
	}
	function setson(son) {
		setTimeout(function(){
			addClass(son,'nu');
			setTimeout(function(){
				removeClass(son,'vu');
			},1000)
		},500)
	}
	
	function addClass(element,name) {
		if(!element.className.match(new RegExp('(^|\\s)'+name+'(\\s|$)'))) {
			element.className += ' ' + name;
		}
	}
	function removeClass(element,name) {
		if(element.className.match(new RegExp('(^|\\s)'+name+'(\\s|$)'))) {
			element.className = element.className.replace(new RegExp('(^|\\s)'+name+'(\\s|$)'),' ');
		}
	}
};
