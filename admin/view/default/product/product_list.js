function go_product_form(product)
{
	if(product.substr(0,1)=='_'){
		return;
	}
	{{$target}}.window.location='?c=product&a=sellForm&product='+product;
}
document.write('<select name="product" onChange=go_product_form(this.value)>');
document.write('<option value="_">--产品快速导航--</option>');
{{foreach from=$products item=product}}
document.write('<option value="{{$product.type}}_{{$product.id}}">{{if $product.type!=''}}&nbsp;&nbsp;{{/if}}{{$product.name}}</option>');
{{/foreach}}
document.write('</select>');