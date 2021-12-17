<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Form\Extension\Core\Type\FileType; 
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\HttpFoundation\UrlHelper;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Reader\Csv as ReaderCsv;
use PhpOffice\PhpSpreadsheet\Reader\Ods as ReaderOds;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as ReaderXlsx;


use App\Application\Output\Output;

class ConfigurationController extends AbstractController
{
    /**
     * @Route("/", name="configuration")
     */
    public function index()
    {
        return $this->render('configuration/index.html.twig', [
            'controller_name' => 'ConfigurationController',
        ]);
    }

    /**
     * @Route("/call/remove/duplicate/record", name="removeDuplicateRecordInSpreadSheet", methods={ "POST" })
     * 
     * @param Request $request
     * 
     * @return Response
     */
    public function removeDuplicateRecordInSpreadSheet(Request $request)
    {   
        $data = [];
        $uploadDir = $this->getParameter('upload_directory');
        try {
            
            //Retail Expense
            $filetype =1 ;
            $ext = pathinfo($_FILES["expense_sheet"]["name"], PATHINFO_EXTENSION);
            $target_file = $uploadDir . basename($_FILES["expense_sheet"]["name"]);
            
            if (move_uploaded_file($_FILES["expense_sheet"]["tmp_name"], $target_file)) {
                $message =  "The file ". htmlspecialchars( basename( $_FILES["expense_sheet"]["name"])). " has been uploaded.";
            }
            $spreadsheet = $this->readFile($target_file);
            $newFileName = $uploadDir .'Retail.xlsx';
            if(file_exists($newFileName)){
                unlink($newFileName);
            } 
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($target_file);
            $duplicateCells = $this->removeUniqueRows($spreadsheet, $filetype);
            $createNewExcel = $this->createNewExcelForRetailExpense($spreadsheet, $newFileName, $duplicateCells, $filetype);

            // GST sheet 
            $filetype2 =2;
            $GSText = pathinfo($_FILES["gst_sheet"]["name"], PATHINFO_EXTENSION);
            $gsttarget_file = $uploadDir . basename($_FILES["gst_sheet"]["name"]);
            
            if (move_uploaded_file($_FILES["gst_sheet"]["tmp_name"], $target_file)) {
                $message .="The file ". htmlspecialchars( basename( $_FILES["gst_sheet"]["name"])). " has been uploaded.";
            }
            $newFileNameForGST = $uploadDir .'GST.xlsx';
            if(file_exists($newFileNameForGST)){
                unlink($newFileNameForGST);
            } 
            $spreadsheetGST = \PhpOffice\PhpSpreadsheet\IOFactory::load($gsttarget_file);
            $duplicateCellforGst = $this->removeUniqueRows($spreadsheetGST, $filetype2);
            $createNewExcel = $this->createNewExcelForRetailExpense($spreadsheetGST, $newFileNameForGST, $duplicateCells, $filetype2);
             // GST sheet 



            // Compare the result
            $retailFile = $uploadDir .'Retail.xlsx';
            $GSTfile = $uploadDir .'GST.xlsx';
            $result = $this->compareExcel($retailFile, $GSTfile);
            $data = [
                "resp" => "ok",
                "status" => $message,
                "Retail_data" => $duplicateCells,
                "gst_data" => $duplicateCellforGst,
                "result" => $result
            ];

        }
        catch (FileException $e) {
            return Output::throwError($e);
        }
        return Output::throwSuccess($data);
        
    }

    protected function removeUniqueRows($objPHPExcel, $filetype) 
    {
        $worksheet = $objPHPExcel->getActiveSheet();
        if($filetype == 1){
            $column = 'G';
            $rowindex_length = 9;
        } else{
            $column = 'A';
            $rowindex_length = 8;
        }
       
        $cells = array();
        foreach ($worksheet->getRowIterator() as $row) {
            $rowIndex = $row->getRowIndex();
            if($rowIndex > $rowindex_length){
                $cellValue = $worksheet->getCell($column.$rowIndex)->getValue();
                if(!in_array($cellValue, $cells) && $cellValue != null){
                    $cells[$rowIndex] = $cellValue; 
                }
            }      
        }
       return $cells;
    }

    protected function createNewExcelForRetailExpense($objPHPExcel, $newFileName, $cells, $filetype)
    {
        $worksheet = $objPHPExcel->getActiveSheet();
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('A1', 'SI.No');
        $sheet->setCellValue('B1', 'Document-Date');
        $sheet->setCellValue('C1', 'GST-NO');
        $sheet->setCellValue('D1', 'Invoice-NO');

        $sheet->setCellValue('E1', 'Invoice-Value');
        $sheet->setCellValue('F1', 'Tax-Value');
        
        $sheet->setCellValue('G1', 'Supplier');
        $sheet->setCellValue('H1', 'IGST AMT');
        $sheet->setCellValue('I1', 'CGST AMT');
        $sheet->setCellValue('J1', 'SGST AMT');

        $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
        $sino = 1;
        $newrow = 2;
        foreach ($cells as $key => $value) {
            if($filetype == 1){
                $formateDate = $worksheet->getCell("C".$key)->getFormattedValue();
                $documentDate = date('d-m-Y', strtotime($formateDate));
                
                $GSTNo = $worksheet->getCell("G".$key)->getValue();
                $InvoiceNo = $worksheet->getCell("J".$key)->getValue();
                $InvoiceValue = $worksheet->getCell("E".$key)->getValue();
                $TaxValue = $worksheet->getCell("O".$key)->getValue();
                $supplier = $worksheet->getCell("F".$key)->getValue();

                $IGSTValue = $worksheet->getCell("P".$key)->getValue();
                $CGSTValue = $worksheet->getCell("S".$key)->getValue();
                $SGSTValue = $worksheet->getCell("U".$key)->getValue();
            } else{

                $documentDate = $worksheet->getCell("E".$key)->getValue();
                $GSTNo = $worksheet->getCell("A".$key)->getValue();
                $InvoiceNo = $worksheet->getCell("C".$key)->getValue();
                
                $InvoiceValue = $worksheet->getCell("F".$key)->getValue();
                $TaxValue = $worksheet->getCell("J".$key)->getValue();
                $supplier = $worksheet->getCell("B".$key)->getValue();

                $IGSTValue = $worksheet->getCell("K".$key)->getValue();
                $CGSTValue = $worksheet->getCell("L".$key)->getValue();
                $SGSTValue = $worksheet->getCell("M".$key)->getValue();
            }

            $spreadsheet->getActiveSheet()->setCellValue('A'.$newrow, $sino);
            $spreadsheet->getActiveSheet()->setCellValue('B'.$newrow, $documentDate);
            $spreadsheet->getActiveSheet()->setCellValue('C'.$newrow, $GSTNo);
            $spreadsheet->getActiveSheet()->setCellValue('D'.$newrow, $InvoiceNo);
            $spreadsheet->getActiveSheet()->setCellValue('E'.$newrow, $InvoiceValue);
            $spreadsheet->getActiveSheet()->setCellValue('F'.$newrow, $TaxValue);
            $spreadsheet->getActiveSheet()->setCellValue('G'.$newrow, $supplier);
            $spreadsheet->getActiveSheet()->setCellValue('H'.$newrow, $IGSTValue);
            $spreadsheet->getActiveSheet()->setCellValue('I'.$newrow, $CGSTValue);
            $spreadsheet->getActiveSheet()->setCellValue('J'.$newrow, $SGSTValue);
            
            $newrow++;
            $sino++;
        }

        $writer->save($newFileName);

        return "Remove the duplicate retail Expense";
    }

    /**
     * @Route("/call/compare/spreadsheet", name="compareTwoDocumentResult", methods={ "POST" })
     * 
     * @param Request $request
     * 
     * @return Response
     */
    public function compareTwoDocumentResult(Request $request)
    {   
        $uploadDir = $this->getParameter('upload_directory');
        $retailFile = $uploadDir .'Retail.xlsx';
        $GSTfile = $uploadDir .'GST.xlsx';
        $result = $this->compareExcel($retailFile, $GSTfile);

        return Output::throwSuccess($result);
        
    }


    protected function compareExcel($retailFile, $GSTfile){
        $resultDate  = array();

        $spreadsheet1 = \PhpOffice\PhpSpreadsheet\IOFactory::load($retailFile);
        $spreadsheet2 = \PhpOffice\PhpSpreadsheet\IOFactory::load($GSTfile);

        $excelsheet = array();
        $excelsheet2 = array();
       
        $worksheet1 = $spreadsheet1->getActiveSheet();
        $rowkey = 0;
        foreach ($worksheet1->getRowIterator() as $row) {
            $rowIndex = $row->getRowIndex();
            $cellIterator = $row->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(false);
            $columnLetter = 'a';
            $columnIndex = 1;
            foreach ($cellIterator as $cell) {
                if($rowIndex > 1){
                    $columnValue = $worksheet1->getCell($columnLetter.$columnIndex)->getValue();
                    $excelsheet[$rowkey][$columnValue] = $cell->getCalculatedValue();
                    $columnLetter++;
                    
                }
               
            }
            if($rowIndex > 1){
                $rowkey++;      
            } 
        }

        $worksheet2 = $spreadsheet2->getActiveSheet();
        $rowkey1 = 0;
        foreach ($worksheet2->getRowIterator() as $row2) {
            $rowIndex2 = $row2->getRowIndex();
            $cellIterator2 = $row2->getCellIterator();
            $cellIterator2->setIterateOnlyExistingCells(false);
            $columnLetter2 = 'a';
            $columnIndex2 = 1;
            foreach ($cellIterator2 as $cell2) {
                if($rowIndex2 > 1){
                    $columnValue2 = $worksheet2->getCell($columnLetter2.$columnIndex2)->getValue();
                    $excelsheet2[$rowkey1][$columnValue2] = $cell2->getCalculatedValue();
                    $columnLetter2++;  
                }
               
            } 
            if($rowIndex2 > 1){
                $rowkey1++;      
            }
        }

        $matchedList = array();
        $NotmactedList = array();
        // echo"<pre>";
        // //print_r($excelsheet);
        // //print_r($excelsheet2);

        if(count($excelsheet) > 0){
            $matchedkey = 0;
            $unmatchedkey = 0;
            foreach ($excelsheet as $key => $excelsheet_value) {
                    $gstNumber = $excelsheet_value['GST-NO'];
                    $supplier = $excelsheet_value['Invoice-NO'];
                    $documentDate = $excelsheet_value['Document-Date'];
                    $searchResult1 = array_search($gstNumber, array_column($excelsheet2, 'GST-NO'));
                    $searchResult2 = array_search($supplier, array_column($excelsheet2, 'Invoice-NO'));
                    $searchResult3 = array_search($documentDate, array_column($excelsheet2, 'Document-Date'));
                   
                    if((!empty($searchResult1) && !empty($searchResult2)) && !empty($searchResult3)){
                        
                        $matchedList[$matchedkey]['Retail'] = $excelsheet_value;  
                        $matchedList[$matchedkey]['GST'] = $excelsheet2[$searchResult1]; 

                        $matchedkey++;
                        
                    } else{
                        $NotmactedList[$unmatchedkey]['Retail'] = $excelsheet_value; 
                        $unmatchedkey++;
                    }
                    
            }
        }
        //print_r($matchedList);

        $resultDate['Matched'] = $matchedList;
        $resultDate['Not_Matched'] = $NotmactedList;

        //$difference = $this->array_diff_assoc_recursive($excelsheet, $excelsheet2); 


       
        //print_r($difference);
        //print_r($excelsheet2);

        return $resultDate;

    }

    protected function array_diff_assoc_recursive($array1, $array2) {
        $difference=array();
        foreach($array1 as $key => $value) {
            if( is_array($value) ) {
                if( !isset($array2[$key]) || !is_array($array2[$key]) ) {
                    $difference[$key] = $value;
                } else {
                    $new_diff = $this->array_diff_assoc_recursive($value, $array2[$key]);
                    if( !empty($new_diff) )
                        $difference[$key] = $new_diff;
                }
            } else if( !array_key_exists($key,$array2) || $array2[$key] !== $value ) {
                $difference[$key] = $value;
            }
        }
        return $difference;
    }


    protected function loadFile($filename)
    {
        return IOFactory::load($filename);
    }

    protected function readFile($filename)
    {
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        switch ($extension) {
            case 'ods':
                $reader = new ReaderOds();
                break;
            case 'xlsx':
                $reader = new ReaderXlsx();
                break;
            case 'xls':
                $reader = new ReaderXlsx();
                break;
            case 'csv':
                $reader = new ReaderCsv();
                break;
            default:
                throw new \Exception('Invalid extension');
        }
        $reader->setReadDataOnly(true);
        return $reader->load($filename);
    }

    protected function createDataFromSpreadsheet($spreadsheet)
    {
        $data = [];
        foreach ($spreadsheet->getWorksheetIterator() as $worksheet) {
            $column = 'G';
            $cell = array();
            foreach ($worksheet->getRowIterator() as $row) {
                $rowIndex = $row->getRowIndex();
                if($rowIndex > 8){
                    //$cellValue = $worksheet->getCellByColumnAndRow($column, $rowIndex)->getValue();
                    $cellValue = $worksheet->getCell($column.$rowIndex)->getValue();
                    array_push($cell, $cellValue);       
                }
            }
            
            $toRemove = array_keys(array_diff($cell, array_diff_assoc($cell, array_unique($cell))));
    
            for ($i = count($toRemove)-1; $i > -1; $i--) {
                $worksheet->removeRow($toRemove[$i]+1);
            }
        }

        return $spreadsheet;

            // foreach ($worksheet->getRowIterator() as $row) {
            //     $rowIndex = $row->getRowIndex();
            //     $cellIterator = $row->getCellIterator();
            //     $cellIterator->setIterateOnlyExistingCells(false);
            //     foreach ($cellIterator as $cell) {
            //         if ($rowIndex > 8) {
            //             //$cellValue = $worksheet->getCell('G8')->getValue();
            //             $data['columnValues'][$rowIndex][] = $cell->getCalculatedValue();
            //         }
            //     }
            //    // array_push($cells, $cellValue); 
            // }
            //$highestRow = $spreadsheet->getActiveSheet()->getHighestRow();

            // $data['G8'] = $spreadsheet->getActiveSheet()->rangeToArray(
            //     'G7',     // The worksheet range that we want to retrieve
            //     NULL,        // Value that should be returned for empty cells
            //     TRUE,        // Should formulas be calculated (the equivalent of getCalculatedValue() for each cell)
            //     TRUE,        // Should values be formatted (the equivalent of getFormattedValue() for each cell)
            //     TRUE         // Should the array be indexed by cell row and cell column
            // );
            // $worksheetTitle = $worksheet->getTitle();
            // $data[$worksheetTitle] = [
            //     'columnNames' => [],
            //     'columnValues' => [],
            // ];
            // foreach ($worksheet->getRowIterator() as $row) {
            //     $rowIndex = $row->getRowIndex();
            //     if ($rowIndex > 2) {
            //         $data[$worksheetTitle]['columnValues'][$rowIndex] = [];
            //     }
            //     $cellIterator = $row->getCellIterator();
            //     $cellIterator->setIterateOnlyExistingCells(false); // Loop over all cells, even if it is not set
            //     foreach ($cellIterator as $cell) {
            //         if ($rowIndex === 2) {
            //             $data[$worksheetTitle]['columnNames'][] = $cell->getCalculatedValue();
            //         }
            //         if ($rowIndex > 2) {
            //             $data[$worksheetTitle]['columnValues'][$rowIndex][] = $cell->getCalculatedValue();
            //         }
            //     }
            // }
        // }

        // $data = $cell;

        // return $data;
    }
}

