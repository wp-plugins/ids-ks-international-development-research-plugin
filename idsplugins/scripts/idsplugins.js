function changeImportSettings() {
  var assets_import_enabled = ($jqorig("#ids_import_documents_checkbox").prop("checked") || $jqorig("#ids_import_organisations_checkbox").prop("checked"));
  if (assets_import_enabled) {
    $jqorig(".ids-assets-import-field").show();
  } else {
    $jqorig(".ids-assets-import-field").hide();
  }

  var categories_import_enabled = ($jqorig("#ids_new_categories").prop("checked") && ($jqorig("#ids_import_countries_checkbox").prop("checked") || $jqorig("#ids_import_regions_checkbox").prop("checked") || $jqorig("#ids_import_themes_checkbox").prop("checked")));
  if (categories_import_enabled) {
    $jqorig(".ids-categories-import-field").show();
  } else {
    $jqorig(".ids-categories-import-field").hide();
  }
}

function changeDataset(selected_dataset) {
  $jqorig.each(['eldis', 'bridge'], function(i, dataset) { 
    if ((dataset == selected_dataset) || (selected_dataset == 'both')) {
      $jqorig('.ids-categories-' + dataset).show();
      if ($jqorig("#ids_map_categories").prop("checked")) {
        $jqorig('.ids-mappings-' + dataset).show();
      }
    }
    else {
      $jqorig('.ids-categories-' + dataset).hide();
      $jqorig('.ids-mappings-' + dataset).hide();
    }
  });
}

function changeMappingsSettings() {
  if ($jqorig("#ids_map_categories").prop("checked")) {
    $jqorig(".ids-categories-mapping").show();
  }
  else {
    $jqorig(".ids-categories-mapping").hide();
  }
}

function changeNewCategories() {
  if ($jqorig("#ids_new_categories").prop("checked")) {
    $jqorig(".ids-categories-new").show();
  }
  else {
    $jqorig(".ids-categories-new").hide();
  }
  changeImportSettings();
}

function addMappings(id_source, id_target, id_mappings) {
  var target_val = $jqorig('#' + id_target + ' option:selected').val();
  var target_title = $jqorig('#' + id_target + ' option:selected').text();
  $jqorig('#' + id_source + ' option:selected').each(function(i, selected){
    value_mapping = $jqorig(selected).val() + ',' + target_val;
    existing_value = $jqorig('#' + id_mappings + ' option[value="' + value_mapping + '"]').val();
    if (existing_value == undefined) {
      title_mapping = $jqorig(selected).text() + ' --> ' + target_title;
      $jqorig('#' + id_mappings).append($jqorig("<option></option>").attr("value", value_mapping).text(title_mapping));
    }
  });
}

function populateSelectBoxes() {
  $jqorig.each(['eldis', 'bridge'], function(i, dataset) {
    /* Create and populate themes trees */
    var id_tree = '#jqxTree_' + dataset;
    var id_dropdown = '#dropDownButton_' + dataset;

    $jqtree(id_tree).jqxTree({ source: ids_array_trees_themes[dataset], height: '200px', width: '350px' });
    $jqtree(id_dropdown).jqxDropDownButton({ width: '350px', height: '25px' });

    var dropDownContent = '<div style="position: relative; margin-left: 3px; margin-top: 5px;">Select themes...</div>';
    $jqtree(id_dropdown).jqxDropDownButton('setContent', dropDownContent);

    $jqtree(id_dropdown).bind('close',function () {
      $jqtree(id_tree).jqxTree('collapseAll');
    });

    /* When an item is expanded, it retrieves the subcategories dynamically. */
    $jqtree(id_tree).bind('expand', function (event) {
      var element = $jqtree(event.args.element);
      var loader = false;
      var loaderItem = null;
      var children = element.find('li');
      $jqtree.each(children, function () {
        var item = $jqtree(id_tree).jqxTree('getItem', this);
        console.log(item);
        var prot = item.value.slice(0,7);
        if (prot == 'http://') {
          loaderItem = item;
          loader = true;
          return false
        };
      });
      if (loader) {
        $jqtree.ajax({
          url: loaderItem.value,
          dataType: 'jsonp',
          success: function (data, status, xhr) {
            $jqtree(id_tree).jqxTree('addTo', data, element[0]);
            $jqtree(id_tree).jqxTree('removeItem', loaderItem.element);
          }
        });
      }
    });

    var id_select_themes = '#' + dataset + '_themes_assets';
    var select_themes = $jqorig(id_select_themes);
    /* When an item is selected, it populates the corresponding chosen select box. */
    $jqtree(id_tree).bind('select', function (event) {
      var args = event.args;
      var item = $jqtree(id_tree).jqxTree('getItem', args.element);
      var object_id = item.value;
      if (object_id !== null) {
        var title = item.label;
        var exists = $jqorig(id_select_themes + ' option[value=' + object_id + ']').length;
        if (exists == 0) {
          select_themes.append($jqorig("<option></option>").attr("value", object_id).text(title));
        }
        $jqorig('#' + dataset + '_themes_assets' + ' option[value="' + object_id +'"]').prop("selected", true);
        $jqchosen(select_themes).trigger("liszt:updated");
      }
    });

    $jqorig.each(['countries', 'regions', 'themes'], function(j, category) { 
      id_select_assets = dataset + '_' + category + '_assets';
      id_select_sources = dataset + '_' + category + '_source';
      id_select_mappings = dataset + '_' + category + '_mappings';

      assets_exist = $jqorig('#' + id_select_assets).length !== 0;;
      sources_exist = $jqorig('#' + id_select_sources).length !== 0;;
      mappings_exist = $jqorig('#' + id_select_mappings).length !== 0;;

      /* Populate the filter select boxes - except themes */
      if (assets_exist) {
        select_category_assets = $jqorig('#' + id_select_assets);
        select_category_assets.empty();
        if (category != 'themes') {
          $jqorig.each(ids_array_categories[dataset][category], function(object_id, title) {
            select_category_assets.append($jqorig("<option></option>").attr("value", object_id).text(title));
          });
        } else {
          $jqorig.each(selected_categories[dataset][category], function(i, object_id) {
            array_object_ids = ids_array_categories[dataset][category];
            title = array_object_ids[object_id];
            select_category_assets.append($jqorig("<option></option>").attr("value", object_id).text(title));
          });
        }
        /* Mark previously selected categories */
        select_category_assets.val(selected_categories[dataset][category]);
      }

      /* Populate mapping sources select boxes */
      if (sources_exist) {
        select_category_sources = $jqorig('#' + id_select_sources);
        select_category_sources.empty();
        $jqorig.each(ids_array_categories[dataset][category], function(object_id, title) {
          select_category_sources.append($jqorig("<option></option>").attr("value", object_id).text(title));
        });
      }

      /* Populate the existing mappings select boxes */
      if (mappings_exist) {
        select_category_mappings = $jqorig('#' + id_select_mappings);
        select_category_mappings.empty();
        $jqorig.each(selected_categories_mappings[dataset][category], function(value, mapping) {
          cats_mapping = mapping.split(',');
          ids_category = cats_mapping[0];
          wp_category = cats_mapping[1];
          wp_category_name = $jqorig('#' + dataset + '_' + category + '_target option[value="' + wp_category + '"]').text();
          title = ids_array_categories[dataset][category][ids_category] + ' --> ' + wp_category_name;
          select_category_mappings.append($jqorig("<option></option>").attr("value", mapping).text(title));
        });
      }
    });
  });
  $jqchosen(".chzn-select").chosen();
  $jqchosen(".chzn-select-deselect").chosen({allow_single_deselect:true});
}

function selectAll(id_cat_field) {
  var id_select = "#" + id_cat_field;
  $jqorig(id_select + ' option').prop('selected', true);
  $jqchosen(id_select).trigger("liszt:updated");
}

function deselectAll(id_cat_field) {
  var id_select = "#" + id_cat_field;
  $jqorig(id_select).val([]);
  $jqchosen(id_select).trigger("liszt:updated");
}

function removeAll(id_cat_field) {
  var id_select = "#" + id_cat_field;
  $jqorig(id_select).empty();
  $jqchosen(id_select).trigger("liszt:updated");
}

function expandTree(tree) {
  $jqtree(tree).jqxTree('expandAll');
}

function collapseTree(tree) {
  $jqtree(tree).jqxTree('collapseAll');
}

function selectAllMappings() {
  $jqorig.each(['eldis', 'bridge'], function(i, dataset) { 
    $jqorig.each(['countries', 'regions', 'themes'], function(j, category) {
      id_select = dataset + '_' + category + '_mappings';
      selectAll(id_select);
    });
  });
}

function removeMappings(id_cat_field) {
  $jqorig("#" + id_cat_field + " option:selected").remove();
}

function updateUsername() {
  var new_user = $jqorig("#ids_import_user_select option:selected").val();
  if (new_user == -1) {
    new_username = default_user;
  }
  else {
    new_username = $jqorig("#ids_import_user_select option:selected").html();
  }
  $jqorig("#ids_import_user").val(new_username);
}

/* default_user is defined in function ids_init_javascript */
function updateDefaultUser() {
  default_user = $jqorig("#ids_import_user").val();
}

function loadAdminPage() {
	$jqorig(document).ready(function($jqorig) {
		$jqorig(".ui-tabs-panel").each(function(index) {
			if (index > 0)
				$jqorig(this).addClass("ui-tabs-hide");
		});
		$jqorig(".ui-tabs").tabs({ fx: { opacity: "toggle", duration: "fast" } });
	});
  initCategoriesArrays();
  changeImportSettings();
  populateSelectBoxes();
  changeMappingsSettings();
  changeNewCategories();
  changeDataset(default_dataset);
  $jqorig("#ids_import_user_select").change(updateUsername); 
  $jqorig("#ids_import_user").change(updateDefaultUser);
}

if (window.addEventListener) {
  window.addEventListener('load', loadAdminPage, false);
} else if (window.attachEvent) {
  window.attachEvent('onload', loadAdminPage);
}

