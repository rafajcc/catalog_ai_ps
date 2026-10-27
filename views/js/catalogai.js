$(document).ready(function() {
	$('#catalogai-generate').on('click', function() {
		var $btn = $(this);
		var $status = $('#catalogai-status');
		var $results = $('#catalogai-results');
		
		$btn.prop('disabled', true);
		$status.text('Enviando datos al servicio Catalog AI...');
		$results.hide();
		
		var productData = {
			id: catalogaiProductId,
			reference: $('#id_product_autocomplete_input, input[name="reference"]').val() || '',
			name: $('#name_1, input[name="name_1"]').val() || '',
			brand: $('#id_manufacturer').val() || '',
			ean: $('input[name="ean13"]').val() || '',
			description: tinyMCE.get('description') ? tinyMCE.get('description').getContent() : ($('#description').val() || ''),
			description_short: tinyMCE.get('description_short') ? tinyMCE.get('description_short').getContent() : ($('#description_short').val() || '')
		};
		
		$.ajax({
			url: catalogaiAjaxUrl + '&ajax=1&action=generate',
			type: 'POST',
			dataType: 'json',
			data: {
				ajax: 1,
				action: 'generate',
				token: catalogaiToken,
				product_id: catalogaiProductId,
				product: JSON.stringify(productData)
			},
			success: function(response) {
				if (response.success && response.data) {
					if (response.data.proposals) {
						if (response.data.proposals.name) {
							$('#catalogai-name').val(response.data.proposals.name);
						}
						if (response.data.proposals.description_short) {
							$('#catalogai-desc-short').val(response.data.proposals.description_short);
						}
						if (response.data.proposals.description) {
							$('#catalogai-desc').val(response.data.proposals.description);
						}
						if (response.data.proposals.meta_title) {
							$('#catalogai-meta-title').val(response.data.proposals.meta_title);
						}
						if (response.data.proposals.meta_description) {
							$('#catalogai-meta-desc').val(response.data.proposals.meta_description);
						}
					}
					if (response.data.image_urls && response.data.image_urls.length > 0) {
						var $images = $('#catalogai-images');
						$images.empty();
						$.each(response.data.image_urls, function(i, url) {
							var $col = $('<div class="col-lg-3" style="margin-bottom:10px;"></div>');
							var $img = $('<img src="' + url + '" style="max-width:100%;max-height:150px;" class="img-thumbnail" />');
							var $btn = $('<button type="button" class="btn btn-default btn-sm catalogai-add-image" style="display:block;margin-top:5px;" data-url="' + url + '"><i class="icon-plus"></i> Añadir imagen</button>');
							$col.append($img).append($btn);
							$images.append($col);
						});
					}
					$results.show();
					$status.text('Sugerencias recibidas. Revisa y aplica las que desees.');
				} else {
					$status.text(response.error || 'Error al obtener sugerencias');
				}
			},
			error: function(xhr) {
				var msg = 'Error de comunicación con el servidor';
				try {
					var resp = JSON.parse(xhr.responseText);
					if (resp.error) msg = resp.error;
				} catch(e) {}
				$status.text(msg);
			},
			complete: function() {
				$btn.prop('disabled', false);
			}
		});
	});
	
	$('.catalogai-apply').on('click', function() {
		var field = $(this).data('field');
		var value = '';
		
		if (field == 'name') value = $('#catalogai-name').val();
		else if (field == 'description_short') {
			if (typeof tinyMCE !== 'undefined' && tinyMCE.get('description_short')) {
				tinyMCE.get('description_short').setContent($('#catalogai-desc-short').val());
				alert('Aplicado al campo descripción corta');
				return;
			}
			$('#description_short').val($('#catalogai-desc-short').val());
		}
		else if (field == 'description') {
			if (typeof tinyMCE !== 'undefined' && tinyMCE.get('description')) {
				tinyMCE.get('description').setContent($('#catalogai-desc').val());
				alert('Aplicado al campo descripción');
				return;
			}
			$('#description').val($('#catalogai-desc').val());
		}
		else if (field == 'meta_title') value = $('#catalogai-meta-title').val();
		else if (field == 'meta_description') value = $('#catalogai-meta-desc').val();
		
		if (field == 'name') {
			$('input[name="name_1"]').val(value).trigger('change');
		} else if (field == 'meta_title') {
			$('input[name="meta_title_1"]').val(value).trigger('change');
		} else if (field == 'meta_description') {
			$('textarea[name="meta_description_1"]').val(value).trigger('change');
		}
		alert('Aplicado correctamente');
	});
	
	$(document).on('click', '.catalogai-add-image', function() {
		var url = $(this).data('url');
		alert('Las imágenes sugeridas se deben añadir manualmente desde la pestaña Imágenes del producto (o mediante el flujo de guardado del servicio).');
	});
});
