!function(t){t.entwine("ss",function(t){t(".select2:input").entwine({onmatch:function(){var e=this,a=t.extend({},this.configuration(e),e.data());if(e.hasClass("has-select2"))return this._super();if(e.data("prefetchUrl")){var r=[];e[0].disabled=!0,e.addClass("processing"),t.ajax({type:"GET",url:e.data("prefetchUrl"),success:function(t){r=t},complete:function(){a.data={results:r},e[0].disabled=!1,e.select2(a),e.removeClass("processing")}})}else e.select2(a);return this._super()},onunmatch:function(){this._super()},configuration:function(t){var e={width:"resolve"};return t.data("suggestUrl")?e.ajax={url:t.data("suggestUrl"),cache:!0,data:function(t){return{q:t}},processResults:function(t){return{results:t}}}:t.data("local")&&(e.data={results:t.data("local")}),t.data("allowHtml")&&(e.escapeMarkup=function(t){return t}),t.attr("required")||(e.allowClear=!0,e.hasOwnProperty("data")&&!this.hasEmptyItem(e.data.results)&&e.data.results.unshift(e.data.results,{id:"",text:t.attr("placeholder")||t.data("placeholder")||""})),t.data("requireSelection")||(e.createSearchChoice=function(t){return{id:t,text:t}}),e},hasEmptyItem:function(t){for(var e in t)if(t.hasOwnProperty(e)&&t.item.hasOwnProperty.id&&!t.item.id)return!0;return!1}})})}(jQuery);