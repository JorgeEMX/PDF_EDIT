PDF_EDIT
========

Clase PHP que permite reemplazar texto en archivos PDF

Ésta clase permite editar texto en objetos tipo stream dentro un PDF que ya ha sido creado, trabaja en conjunto con
TCPDF Parser (ver proyecto TCPDF http://sourceforge.net/projects/tcpdf/). Por el momento, la clase se construyó para
un propósito muy específico y se ha subido a GITHUB para que sirva de referencia a otros desarrolladores que deseen
editar un archivo ya creado.

ADVERTENCIA
========
Ya que se hace uso de la funcionalidad de TCPDF Parser, los archivos que se podrán leer serán aquellos con
las características que soporte TCPDF Parser.

USO
========

// Instancia de PDF_EDIT para modificar el archivo

.....
	
// Se reemplaza el texto TCPDF por PDF_EDIT

$parser->ReplaceText('TCPDF', 'PDF_EDIT');
	
// Se obtiene el archivo editado

$new_pdf = $parser->Output();

REFERENCIA OBLIGADA
========
http://www.adobe.com/content/dam/Adobe/en/devnet/acrobat/pdfs/pdf_reference_1-7.pdf
