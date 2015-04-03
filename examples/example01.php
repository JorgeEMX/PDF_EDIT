<?php
	
	// PDF_EDIT Class
	require_once(dirname(dirname(__FILE__)).'/pdf_edit.php');
	
	// Instancia de PDF_EDIT para modificar el archivo
	$parser = new PDF_EDIT(file_get_contents('example_001.pdf'));
	
	// Se reemplaza el texto TCPDF por PDF_EDIT
	$parser->ReplaceText('TCPDF', 'PDF_EDIT');
    
    // Se reemplaza library por class
    $parser->ReplaceText('library', 'class');
	
    // Prueba de bug, texto con signos (se reemplaza text por /text)
    $parser->ReplaceText('text', '/[text]');
    
	// Se obtiene el archivo editado
	$new_pdf = $parser->Output();
	
	// Se muestra en el navegador
	header("Content-Type: application/pdf");
	header('Content-Disposition: inline; filename="FileEdit.pdf"');
	header('Content-Transfer-Encoding: binary');
	header('Accept-Ranges: bytes');
	echo $new_pdf;

?>
