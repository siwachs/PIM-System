<?php

/**
 * Fields Summary:
 * - motherboard [manyToManyObjectRelation]
 * - operatingSystem [manyToManyObjectRelation]
 * - processor [manyToManyObjectRelation]
 * - ram [manyToManyObjectRelation]
 * - rom [manyToManyObjectRelation]
 * - vram [manyToManyObjectRelation]
 * - connectivityTechnology [multiselect]
 */

return Pimcore\Model\DataObject\Objectbrick\Definition::__set_state(array(
   'dao' => NULL,
   'key' => 'AdvanceTechnicalDetails',
   'parentClass' => '',
   'implementsInterfaces' => '',
   'title' => '',
   'group' => '',
   'layoutDefinitions' => 
  Pimcore\Model\DataObject\ClassDefinition\Layout\Panel::__set_state(array(
     'name' => NULL,
     'type' => NULL,
     'region' => NULL,
     'title' => NULL,
     'width' => 0,
     'height' => 0,
     'collapsible' => false,
     'collapsed' => false,
     'bodyStyle' => NULL,
     'datatype' => 'layout',
     'children' => 
    array (
      0 => 
      Pimcore\Model\DataObject\ClassDefinition\Layout\Panel::__set_state(array(
         'name' => 'Advance Technical Details',
         'type' => NULL,
         'region' => NULL,
         'title' => 'Advance Technical Details',
         'width' => '',
         'height' => '',
         'collapsible' => false,
         'collapsed' => false,
         'bodyStyle' => '',
         'datatype' => 'layout',
         'children' => 
        array (
          0 => 
          Pimcore\Model\DataObject\ClassDefinition\Data\ManyToManyObjectRelation::__set_state(array(
             'name' => 'motherboard',
             'title' => 'Motherboard',
             'tooltip' => '',
             'mandatory' => false,
             'noteditable' => false,
             'index' => false,
             'locked' => false,
             'style' => '',
             'permissions' => NULL,
             'fieldtype' => '',
             'relationType' => true,
             'invisible' => false,
             'visibleGridView' => false,
             'visibleSearch' => false,
             'blockedVarsForExport' => 
            array (
            ),
             'classes' => 
            array (
              0 => 
              array (
                'classes' => 'MotherBoard',
              ),
            ),
             'displayMode' => 'grid',
             'pathFormatterClass' => '',
             'maxItems' => 1,
             'visibleFields' => 'id,name,Brand',
             'allowToCreateNewObject' => false,
             'allowToClearRelation' => true,
             'optimizedAdminLoading' => false,
             'enableTextSelection' => false,
             'visibleFieldDefinitions' => 
            array (
            ),
             'width' => '',
             'height' => '',
          )),
          1 => 
          Pimcore\Model\DataObject\ClassDefinition\Data\ManyToManyObjectRelation::__set_state(array(
             'name' => 'operatingSystem',
             'title' => 'Operating System',
             'tooltip' => '',
             'mandatory' => false,
             'noteditable' => false,
             'index' => false,
             'locked' => false,
             'style' => '',
             'permissions' => NULL,
             'fieldtype' => '',
             'relationType' => true,
             'invisible' => false,
             'visibleGridView' => false,
             'visibleSearch' => false,
             'blockedVarsForExport' => 
            array (
            ),
             'classes' => 
            array (
              0 => 
              array (
                'classes' => 'OperatingSystem',
              ),
            ),
             'displayMode' => 'grid',
             'pathFormatterClass' => '',
             'maxItems' => 1,
             'visibleFields' => 'id,name,Brand',
             'allowToCreateNewObject' => false,
             'allowToClearRelation' => true,
             'optimizedAdminLoading' => false,
             'enableTextSelection' => false,
             'visibleFieldDefinitions' => 
            array (
            ),
             'width' => '',
             'height' => '',
          )),
          2 => 
          Pimcore\Model\DataObject\ClassDefinition\Data\ManyToManyObjectRelation::__set_state(array(
             'name' => 'processor',
             'title' => 'Processor',
             'tooltip' => '',
             'mandatory' => false,
             'noteditable' => false,
             'index' => false,
             'locked' => false,
             'style' => '',
             'permissions' => NULL,
             'fieldtype' => '',
             'relationType' => true,
             'invisible' => false,
             'visibleGridView' => false,
             'visibleSearch' => false,
             'blockedVarsForExport' => 
            array (
            ),
             'classes' => 
            array (
              0 => 
              array (
                'classes' => 'Processor',
              ),
            ),
             'displayMode' => 'grid',
             'pathFormatterClass' => '',
             'maxItems' => 1,
             'visibleFields' => 'id,name,Brand',
             'allowToCreateNewObject' => false,
             'allowToClearRelation' => true,
             'optimizedAdminLoading' => false,
             'enableTextSelection' => false,
             'visibleFieldDefinitions' => 
            array (
            ),
             'width' => '',
             'height' => '',
          )),
          3 => 
          Pimcore\Model\DataObject\ClassDefinition\Data\ManyToManyObjectRelation::__set_state(array(
             'name' => 'ram',
             'title' => 'RAM',
             'tooltip' => '',
             'mandatory' => false,
             'noteditable' => false,
             'index' => false,
             'locked' => false,
             'style' => '',
             'permissions' => NULL,
             'fieldtype' => '',
             'relationType' => true,
             'invisible' => false,
             'visibleGridView' => false,
             'visibleSearch' => false,
             'blockedVarsForExport' => 
            array (
            ),
             'classes' => 
            array (
              0 => 
              array (
                'classes' => 'Storage',
              ),
            ),
             'displayMode' => 'grid',
             'pathFormatterClass' => '',
             'maxItems' => 1,
             'visibleFields' => 'id,name,brand',
             'allowToCreateNewObject' => false,
             'allowToClearRelation' => true,
             'optimizedAdminLoading' => false,
             'enableTextSelection' => false,
             'visibleFieldDefinitions' => 
            array (
            ),
             'width' => '',
             'height' => '',
          )),
          4 => 
          Pimcore\Model\DataObject\ClassDefinition\Data\ManyToManyObjectRelation::__set_state(array(
             'name' => 'rom',
             'title' => 'ROM',
             'tooltip' => '',
             'mandatory' => false,
             'noteditable' => false,
             'index' => false,
             'locked' => false,
             'style' => '',
             'permissions' => NULL,
             'fieldtype' => '',
             'relationType' => true,
             'invisible' => false,
             'visibleGridView' => false,
             'visibleSearch' => false,
             'blockedVarsForExport' => 
            array (
            ),
             'classes' => 
            array (
              0 => 
              array (
                'classes' => 'Storage',
              ),
            ),
             'displayMode' => 'grid',
             'pathFormatterClass' => '',
             'maxItems' => 1,
             'visibleFields' => 'id,name,brand',
             'allowToCreateNewObject' => false,
             'allowToClearRelation' => true,
             'optimizedAdminLoading' => false,
             'enableTextSelection' => false,
             'visibleFieldDefinitions' => 
            array (
            ),
             'width' => '',
             'height' => '',
          )),
          5 => 
          Pimcore\Model\DataObject\ClassDefinition\Data\ManyToManyObjectRelation::__set_state(array(
             'name' => 'vram',
             'title' => 'VRAM',
             'tooltip' => '',
             'mandatory' => false,
             'noteditable' => false,
             'index' => false,
             'locked' => false,
             'style' => '',
             'permissions' => NULL,
             'fieldtype' => '',
             'relationType' => true,
             'invisible' => false,
             'visibleGridView' => false,
             'visibleSearch' => false,
             'blockedVarsForExport' => 
            array (
            ),
             'classes' => 
            array (
              0 => 
              array (
                'classes' => 'Storage',
              ),
            ),
             'displayMode' => 'grid',
             'pathFormatterClass' => '',
             'maxItems' => 1,
             'visibleFields' => 'id,name,brand',
             'allowToCreateNewObject' => false,
             'allowToClearRelation' => true,
             'optimizedAdminLoading' => false,
             'enableTextSelection' => false,
             'visibleFieldDefinitions' => 
            array (
            ),
             'width' => '',
             'height' => '',
          )),
          6 => 
          Pimcore\Model\DataObject\ClassDefinition\Data\Multiselect::__set_state(array(
             'name' => 'connectivityTechnology',
             'title' => 'Connectivity Technology',
             'tooltip' => '',
             'mandatory' => false,
             'noteditable' => false,
             'index' => false,
             'locked' => false,
             'style' => '',
             'permissions' => NULL,
             'fieldtype' => '',
             'relationType' => false,
             'invisible' => false,
             'visibleGridView' => false,
             'visibleSearch' => false,
             'blockedVarsForExport' => 
            array (
            ),
             'options' => 
            array (
              0 => 
              array (
                'key' => '5G',
                'value' => '5G',
              ),
              1 => 
              array (
                'key' => '4G',
                'value' => '4G',
              ),
              2 => 
              array (
                'key' => '4G VOLTE',
                'value' => '4G VOLTE',
              ),
              3 => 
              array (
                'key' => '3G',
                'value' => '3G',
              ),
              4 => 
              array (
                'key' => '2G',
                'value' => '2G',
              ),
              5 => 
              array (
                'key' => 'GSM',
                'value' => 'GSM',
              ),
              6 => 
              array (
                'key' => 'WCDMA',
                'value' => 'WCDMA',
              ),
              7 => 
              array (
                'key' => 'WIFI',
                'value' => 'WIFI',
              ),
              8 => 
              array (
                'key' => 'Bluetooth',
                'value' => 'Bluetooth',
              ),
              9 => 
              array (
                'key' => 'Radio',
                'value' => 'Radio',
              ),
              10 => 
              array (
                'key' => 'NFC',
                'value' => 'NFC',
              ),
              11 => 
              array (
                'key' => 'Ethernet',
                'value' => 'Ethernet',
              ),
              12 => 
              array (
                'key' => 'Zigbee',
                'value' => 'Zigbee',
              ),
            ),
             'maxItems' => NULL,
             'renderType' => 'list',
             'dynamicOptions' => false,
             'height' => '',
             'width' => '',
             'optionsProviderType' => 'configure',
             'optionsProviderClass' => 'Pimcore\\Bundle\\CoreBundle\\OptionsProvider\\SelectOptionsOptionsProvider',
             'optionsProviderData' => '',
          )),
        ),
         'locked' => false,
         'blockedVarsForExport' => 
        array (
        ),
         'fieldtype' => 'panel',
         'layout' => NULL,
         'border' => false,
         'icon' => '',
         'labelWidth' => 100,
         'labelAlign' => 'left',
      )),
    ),
     'locked' => false,
     'blockedVarsForExport' => 
    array (
    ),
     'fieldtype' => 'panel',
     'layout' => NULL,
     'border' => false,
     'icon' => NULL,
     'labelWidth' => 100,
     'labelAlign' => 'left',
  )),
   'fieldDefinitionsCache' => NULL,
   'blockedVarsForExport' => 
  array (
  ),
   'classDefinitions' => 
  array (
  ),
   'activeDispatchingEvents' => 
  array (
  ),
));
