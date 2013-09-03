
function RecheckAll(field)
{
	//var f = document.getElementById(field);
	var i=0;
while (document.getElementById(field+i)!=null)
{
	document.getElementById(field+i).checked = !document.getElementById(field+i).checked;
	i++;
}
}

function ToggleVisible(d) 
{
	if(document.getElementById(d).style.display=="inline-block")
		document.getElementById(d).style.display = "none";
	else
		document.getElementById(d).style.display = "inline-block";
}

function ToggleVisibleAll(d,s)
{
s = typeof(s) != 'undefined' ? s : 1;

var i=0;
while (document.getElementById(d+i)!=null)
{
	if(s==0)//(document.getElementById(d+i).style.display=="inline-block")
		document.getElementById(d+i).style.display = "none";
	else
		document.getElementById(d+i).style.display = "inline-block";
	i++;
}

}