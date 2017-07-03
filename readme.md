# REDCap Action Tags for Customizing PDF Output

## Installation
1. Copy `pdf_action_tags.php` to a folder inside `redcap` root folder,  e.g. `redcap/customizations/`.
2. Modify the REDCap code file `/PDF/index.php` and insert the following code just before the call to `renderPDF` (at the end of the file):

```php
// include custom code
include dirname(__FILE__)."/../../customizations/pdf_action_tags.php";
// apply custom action tags
$metadata = gr_apply_pdf_actiontags($metadata, $Data);
```

   Unfortunately, this has to be done as there is no suitable hook to inject the custom code.
   
## Action Tags
- `@HIDDEN-PDF`

   When present in a field, it will be excluded from the PDF.

- `@PDF-HIDDENDATA[="field_name"]`
   
   Omits a field from the PDF for 'form with saved data' only. If the name of another field is supplied as parameter, the tagged field will be omitted from 'form with saved data' when actual data is present in the specified field.

- `@PDF-HIDDENNODATA[="field_name"]`
   
   Omits a field from the PDF for 'form (empty)' or for 'form with saved data' when no actual data is present in the field specified in the parameter.

- `@PDF-NOENUM`
   
   When present in a field (sql, select, radio), the enumeration of the list members will be suppressed. Instead, the field is “converted” to a text field, i.e. it will be represented with an empty line. When a data value is present, the value will be preserved.

- `@PDF-DATANOENUM`
   
   Behaves like @PDF-NOENUM, but only applies to 'form with saved data'.

- `@PDF-WHITESPACE="number of lines"`
   
   This action tag only applies to 'form (empty)' PDFs. If present, it will add the given number of empty lines to a field's label, pushing down the next field on the page. 'number of lines' must be a positive integer.

- `@PDF-FIELDNOTEEMPTY="text"`
   
   This action tag only applies to 'form (empty)' PDFs. When set, it replaces the field note of a field with the text supplied as parameter.

- `@PDF-FIELDNOTEDATA="text"`
   
   This action tag only applies to 'form with saved data' PDFs. When set, it replaces the field note of a field with the text supplied as the parameter, but only when there is an actual data value present in the field.
