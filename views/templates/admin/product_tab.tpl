<div class="panel product-tab">
	<h3><i class="icon-magic"></i> {l s='Catalog AI - Enriquecimiento de producto' mod='catalogai'}</h3>
	<p class="help-block">{l s='Envía los datos del producto al servicio Catalog AI para recibir descripciones y/o imágenes sugeridas. Revisa los resultados antes de aplicarlos.' mod='catalogai'}</p>
	
	<div class="form-group">
		<button type="button" id="catalogai-generate" class="btn btn-primary">
			<i class="icon-refresh"></i> {l s='Generar sugerencias' mod='catalogai'}
		</button>
		<span id="catalogai-status" class="text-muted" style="margin-left:10px;"></span>
	</div>
	
	<div id="catalogai-results" style="display:none;">
		<h4>{l s='Sugerencias recibidas' mod='catalogai'}</h4>
		<div class="form-group">
			<label>{l s='Nombre sugerido' mod='catalogai'}</label>
			<textarea id="catalogai-name" class="form-control" rows="2"></textarea>
			<button type="button" class="btn btn-default btn-sm catalogai-apply" data-field="name">{l s='Aplicar al producto' mod='catalogai'}</button>
		</div>
		<div class="form-group">
			<label>{l s='Descripción corta sugerida' mod='catalogai'}</label>
			<textarea id="catalogai-desc-short" class="form-control" rows="3"></textarea>
			<button type="button" class="btn btn-default btn-sm catalogai-apply" data-field="description_short">{l s='Aplicar al producto' mod='catalogai'}</button>
		</div>
		<div class="form-group">
			<label>{l s='Descripción sugerida' mod='catalogai'}</label>
			<textarea id="catalogai-desc" class="form-control" rows="6"></textarea>
			<button type="button" class="btn btn-default btn-sm catalogai-apply" data-field="description">{l s='Aplicar al producto' mod='catalogai'}</button>
		</div>
		<div class="form-group">
			<label>{l s='Meta título sugerido' mod='catalogai'}</label>
			<input type="text" id="catalogai-meta-title" class="form-control" />
			<button type="button" class="btn btn-default btn-sm catalogai-apply" data-field="meta_title">{l s='Aplicar al producto' mod='catalogai'}</button>
		</div>
		<div class="form-group">
			<label>{l s='Meta descripción sugerida' mod='catalogai'}</label>
			<textarea id="catalogai-meta-desc" class="form-control" rows="3"></textarea>
			<button type="button" class="btn btn-default btn-sm catalogai-apply" data-field="meta_description">{l s='Aplicar al producto' mod='catalogai'}</button>
		</div>
		<div class="form-group">
			<label>{l s='Imágenes sugeridas' mod='catalogai'}</label>
			<div id="catalogai-images" class="row"></div>
		</div>
	</div>
</div>

<script type="text/javascript">
	var catalogaiAjaxUrl = '{$catalogai_ajax_url|escape:'javascript':'UTF-8'}';
	var catalogaiToken = '{$catalogai_token|escape:'javascript':'UTF-8'}';
	var catalogaiProductId = {$catalogai_product->id|intval};
</script>
