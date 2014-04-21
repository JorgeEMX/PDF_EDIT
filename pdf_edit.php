<?php

require_once(dirname(__FILE__).'/tcpdf/tcpdf_parser.php');

/**
 * PDF_EDIT
 *
 * Clase en conjunto con TCPDF_PARSER para reemplazar texto en un archivo
 * con formato PDF. Considerar que sólo busca en objetos tipo stream y regenera
 * la definición de Portable Document Format para generar un archivo válido.
 *
 * IMPORTANTE: Ésta clase se generó para un propósito muy especifico, muy probablemente
 * podría generar resultados inesperados sí se utiliza es escenarios diferentes.
 * Referencia obligada: http://www.adobe.com/content/dam/Adobe/en/devnet/acrobat/pdfs/pdf_reference_1-7.pdf
 *
 * @author Jorge Esteban Aguero Alvarez <jorgeaguero.mx [at] gmail [dot] com>
 * @package PDF_EDIT
 */
class PDF_EDIT extends TCPDF_PARSER
{
	/**
	 * Offset de los objetos
	 *
	 * @access private
	 * @var array Offset de los objetos
	 */
	private $objects_offsets= array();

	/**
	 * Tabla de referencia cruzada
	 *
	 * @access private
	 * @var array Tabla de referencia cruzada
	 */
	private $xref_definition= array();

	/**
	 * Contenido de los objetos
	 *
	 * @access private
	 * @var array Contenido de los objetos
	 */
	private $objects_content= array();

	/**
	 * PDF
	 *
	 * @access private
	 * @var string PDF String
	 */
	private $string_pdf		= array();

	public function __construct($pdf_string)
	{
		parent::__construct($pdf_string);
		$this->objects_content		= $this->GetObjects();
		$this->xref_definition		= $this->GetXrefDataDefinition();
	}

	/**
	 * Genera el string del archivo pdf
	 *
	 * Construye la definición de los objetos, el header, la tabla de referencia cruzada y
	 * el trailer para obtener un PDF valido (este método se debe de invocar siempre que
	 * se actualice el contenido de los objetos).
	 *
	 * @return string
	 *
	 */
	private function BuildPDFString()
	{
		$this->string_pdf = "";
		$this->string_pdf = $this->xref_definition['header'];
		foreach($this->objects_content as $obj)
		{
			$this->string_pdf .= $obj;
		}
		$star_xref_offset = strlen($this->string_pdf);

		$this->string_pdf.= $this->updateOffsetsObjects($this->string_pdf, $this->xref_definition['xref']);
		$this->string_pdf.= $this->xref_definition['trailer'];

		$this->xref_definition['startxref'] = preg_replace('/\d+/i', $star_xref_offset, $this->xref_definition['startxref']);
		$this->string_pdf.= $this->xref_definition['startxref'];
	}

    /**
	 * Carga el header, tabla de referencia, el trailer usado por el PDF
	 *
	 * Carga las siguientes definiciones que son utilizadas por los léctores de
	 * PDF:
	 *
	 * herder:		Contiene la versión de Portable Document Format utilizado en el archivo
	 * xref:		Tabla de referencia cruzada que define el offset (entre otros datos)
	 * 				de cada objeto en el archivo
	 * trailer:		Contiene información gral del archivo, por ejemplo la cantidad de objetos
	 * 				definidos
	 * startxref:	Indica a los lectores dónde encontrar la tabla de referencia cruzada para
	 * 				poder leerla
	 *
	 *
	 * @return array
	 *
	 */
    private function GetXrefDataDefinition()
    {
        $obj_arr = array('xref' => '', 'trailer' => '','startxref' => '', 'header' => '');

		$obj_arr['header']	= "%PDF-1.4\n%".pack('H*', "E2E3CFD3")."\n";
		$pos_open       = strpos($this->pdfdata, 'xref', 0);

        if(preg_match_all('/\d+\s\d+/', $this->pdfdata, $matches, PREG_PATTERN_ORDER, $pos_open)){
            $content_obj = "xref\n";
            $content_obj .= $matches[0][0]."\n";

            if (preg_match_all('/\d{10}\s\d{5}\s[fn]/', $this->pdfdata, $matches, PREG_OFFSET_CAPTURE, $pos_open)) {

                foreach($matches[0] as $x => $match)
                {
					$content_obj .= $match[0]." \n";
                }

                if(!empty($content_obj)){
                    $obj_arr['xref'] = $content_obj;
                }
            }
            if (preg_match_all('/trailer/', $this->pdfdata, $matches, PREG_OFFSET_CAPTURE, $pos_open)) {
                $content_obj= "";
                $pos_end    = strpos($this->pdfdata, '>>', $matches[0][0][1]);

                $content_obj = substr($this->pdfdata, $matches[0][0][1], ($pos_end-$matches[0][0][1])+2);
                if(!empty($content_obj)){
                    $obj_arr['trailer'] = $content_obj."\n";
                }

            }
            $content_obj= "";
            $pos_open    = strpos($this->pdfdata, 'startxref', $pos_open);
            $content_obj = substr($this->pdfdata, $pos_open);
            if(!empty($content_obj)){
                $obj_arr['startxref'] = $content_obj;
            }
        }

        return $obj_arr;
    }

	/**
	 * Carga el contenido de cada objeto utilizado por el PDF
	 *
	 * Guarda el contenido de los objetos en el arreglo $this->objects_content,
	 * cada indice del arreglo es llamado de la misma forma que lo hace
	 * TCPDF_PARSER
	 *
	 * @return array
	 *
	 */
    private function GetObjects()
    {
        $obj_arr = array();

        if (preg_match_all('/[0-9]+[\s][0-9]+[\s]obj/i', $this->pdfdata, $matches, PREG_OFFSET_CAPTURE, 0)) {
            foreach($matches[0] as $match)
            {
                $name_obj   = str_replace(' ','_',$match[0]);
                $name_obj   = str_replace('_obj','',$name_obj);
                $offset     = $match[1];

				$this->objects_offsets[$name_obj]= $offset;

                $pos_end        = strpos($this->pdfdata, 'endobj', $offset);
				$content_obj    = substr($this->pdfdata, $offset, ($pos_end-$offset)+6);

                if(!array_key_exists($name_obj, $obj_arr)){
                    $obj_arr[$name_obj] = array();
                }
                $obj_arr[$name_obj] = $content_obj."\n";
            }
        }
        return $obj_arr;
    }

	/**
	 * Actualiza la tabla de referencia cruzada
	 *
	 * Actualiza el offset de cada objeto definido en el archivo y recrea nuevamente
	 * la tabla de referencia cruzada.
	 *
	 * @param string $pdf_data 	Contiene el string generado por el contenido de todos
	 * 							los objetos
	 * @param string $xref_data Contiene la tabla de referencia cruzada que es la que
	 * 							se necesita actualizar
	 *
	 * @return string
	 *
	 */
	private function updateOffsetsObjects($pdf_data, $xref_data)
	{
		if (preg_match_all('/[0-9]+[\s][0-9]+[\s]obj/i', $pdf_data, $matches, PREG_OFFSET_CAPTURE, 0)) {
            foreach($matches[0] as $match)
            {
                $name_obj   = str_replace(' ','_',$match[0]);
                $name_obj   = str_replace('_obj','',$name_obj);
                $offset     = $match[1];

				$this->objects_offsets[$name_obj]= $offset;
            }
        }

		$content_obj = "";
        if(preg_match_all('/\d+\s\d+/', $xref_data, $matches, PREG_PATTERN_ORDER, 0)){
            $content_obj = "xref\n";
            $content_obj .= $matches[0][0]."\n";

            if (preg_match_all('/\d{10}\s\d{5}\s[fn]/', $xref_data, $matches, PREG_OFFSET_CAPTURE, 0)) {

                foreach($matches[0] as $x => $match)
                {
					if($x > 0)
					{
						$name_obj = $x . "_0";
						$arr = explode(" ", $match[0]);
						$content_obj .= str_pad($this->objects_offsets[$name_obj],10,"0", STR_PAD_LEFT)." ".$arr[1]." ".$arr[2]." \n";
					}
					else
					{
						$content_obj .= $match[0]." \n";
					}
                }
            }
        }

        return $content_obj;
	}

	/**
	 * Establece el formato del texto a reemplazar
	 *
	 * Verifica sí el texto que se intenta reemplazar tiene formato. Si es así,
	 * el texto nuevo lo coloca con el mismo formato. Considerar que actualmente,
	 * sólo detecta [\\0].
	 *
	 * @param string $matches		Coincidencias encontradas, si tiene formato
	 * 								count($matches) será mayor que 1
	 * @param string $replace		Texto a reemplazar y que se convertirá al formato
	 */
	private function MakeFormatString($matches, $replace)
	{
		if(count($matches) > 1)
		{
			$string_format = $matches[1][0];
			$replace = chunk_split($replace, 1, $string_format);
		}
		return $replace;
	}

	/**
	 * Reemplaza texto encontrado en el archivo
	 *
	 * @param string $search 			Texto a buscar y que será reemplazado
	 * @param string $replace 			Texto que reemplazará a $search
	 * @param bool	$case_sensitive		Texto sensible a mayúsculas o minúsculas
	 * @param mixed $page 				Indica la pagina dónde se buscará el texto, en desuso
	 *
	 */
    public function ReplaceText($search, $replace, $case_sensitive = true, $page = "all")
    {
		$info   = $this->getParsedData();
		$objs   = $info[1];

		$search = chunk_split($search, 1, '([\\0])?');
		$search = '/'.$search.'/';
		$search = (!$case_sensitive) ? $search.'i' : $search;
		$search.= 'u';

		foreach($objs as $indice => $obj){

			if(array_key_exists(1, $obj) AND $obj[1][0] == "stream")
			{
				$content_decode = $obj[1][3][0];
				$content_encode = $obj[1][1];

				if(!empty($content_decode)){
					$count 			= 0;
					$count_all		= 0;
					$new_content	= "";

					$max_length = 4096;
					if(strlen($content_decode) > $max_length)
					{
						$start_cut		= 0;
						do{
							$replace_format	= $replace;
							if(preg_match($search, substr($content_decode, $start_cut, $max_length), $matches, PREG_OFFSET_CAPTURE, 0)){
								$replace_format	 = $this->MakeFormatString($matches, $replace_format);
							}

							$new_content.= preg_replace($search, $replace_format, substr($content_decode, $start_cut, $max_length), -1, $count);
							$count_all 	+= $count;
							$start_cut	+= $max_length;
						}
						while( ($start_cut+$max_length) <  strlen($content_decode));

						if($start_cut < strlen($content_decode)){
							$replace_format	= $replace;
							if(preg_match($search, substr($content_decode, $start_cut, $max_length), $matches, PREG_OFFSET_CAPTURE, 0)){
								$replace_format	 = $this->MakeFormatString($matches, $replace_format);
							}
							$new_content.= preg_replace($search, $replace_format, substr($content_decode, $start_cut), -1, $count);
							$count_all 	+= $count;
						}
					}
					else{
						if(preg_match($search, $content_decode, $matches, PREG_OFFSET_CAPTURE, 0)){
							$replace 	 = $this->MakeFormatString($matches, $replace);
						}
						$new_content = preg_replace($search, $replace, $content_decode, -1, $count);
						$count_all = $count;
					}

					if($count_all > 0){

						if(preg_match_all('/flatedecode/i', $this->objects_content[$indice], $matches, PREG_PATTERN_ORDER, 0)){
							if($new_content = gzcompress($new_content))
							{
								$name_obj_mod[] = array(
									'name_obj' 		=> $indice,
									'new_content'	=> $new_content,
									'size'			=> strlen($new_content),
								);
							}
						}
						else{
							$name_obj_mod[] = array(
								'name_obj' 		=> $indice,
								'new_content'	=> $new_content,
								'size'			=> strlen($new_content),
							);
						}
					}
					unset($content_decode, $new_content);
				}
			}
		}

		if(count($name_obj_mod) > 0)
		{
			foreach($name_obj_mod as $mod_obj)
			{
				$name 		= $mod_obj['name_obj'];
				$new_content= $mod_obj['new_content'];
				$obj_length = $mod_obj['size'];

				if(array_key_exists($name, $this->objects_content)){

					$stream_obj = preg_replace('/\/Length\s\d+/', '/Length '.$obj_length, $this->objects_content[$name]);

					if(preg_match('/>>\s?stream/', $stream_obj, $result, PREG_OFFSET_CAPTURE))
					{
						$pos_open	= $result[0][1];
						$pos_end	= strpos($stream_obj, 'endstream', 0);
						$content	= substr($stream_obj, 0, $pos_open+strlen($result[0][0]));

						$content.= "\n".$new_content;
						$content.= "\nendstream\n";
						$content.= "endobj\n";

						$this->objects_content[$name] = $content;
					}
				}
			}
		}
		$this->BuildPDFString();
    }

	/**
	 * Devuelve el string del PDF
	 *
	 * @return string
	 *
	 */
	public function Output()
	{
        return (empty($this->string_pdf)) ? $this->pdfdata : $this->string_pdf;
	}
}
?>
