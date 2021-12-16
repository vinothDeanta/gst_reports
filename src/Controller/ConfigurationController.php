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
        $filetype = $request->get('file_type');
        $data = [];
        $uploadDir = $this->getParameter('upload_directory');
        try {
            $ext = pathinfo($_FILES["excel_file"]["name"], PATHINFO_EXTENSION);
            $target_file = $uploadDir . basename($_FILES["excel_file"]["name"]);
            
            if (move_uploaded_file($_FILES["excel_file"]["tmp_name"], $target_file)) {
                $message =  "The file ". htmlspecialchars( basename( $_FILES["excel_file"]["name"])). " has been uploaded.";
            }

            $spreadsheet = $this->readFile($target_file);

            if($filetype == 1){
                $newFileName = $uploadDir .'Retail.xlsx';
                if(file_exists($newFileName)){
                    unlink($newFileName);
                } 
                $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($target_file);
                $duplicateCells = $this->removeUniqueRows($spreadsheet, $filetype);
                $createNewExcel = $this->createNewExcelForRetailExpense($spreadsheet, $newFileName, $duplicateCells, $filetype);
            } else{
                $newFileName = $uploadDir .'GST.xlsx';
                if(file_exists($newFileName)){
                    unlink($newFileName);
                } 
                $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($target_file);
                $duplicateCells = $this->removeUniqueRows($spreadsheet, $filetype);
                $createNewExcel = $this->createNewExcelForRetailExpense($spreadsheet, $newFileName, $duplicateCells, $filetype);
            }

            $data[] = [
            "status" => $message,
            "filetype" => $filetype,
            "extension" => $ext,
            "exceldata" => $duplicateCells,
            "excel_status" => $createNewExcel
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
                $documentDate = $worksheet->getCell("C".$key)->getValue();
    
                $GSTNo = $worksheet->getCell("G".$key)->getValue();
                $InvoiceNo = $worksheet->getCell("J".$key)->getValue();
                $InvoiceValue = $worksheet->getCell("E".$key)->getValue();
                $TaxValue = $worksheet->getCell("O".$key)->getValue();
                $supplier = $worksheet->getCell("H".$key)->getValue();

                $IGSTValue = $worksheet->getCell("P".$key)->getValue();
                $CGSTValue = $worksheet->getCell("S".$key)->getValue();
                $SGSTValue = $worksheet->getCell("U".$key)->getValue();
            } else{

                $documentDate = $worksheet->getCell("E".$key)->getValue();
                $GSTNo = $worksheet->getCell("A".$key)->getValue();
                $InvoiceNo = $worksheet->getCell("C".$key)->getValue();
                
                $InvoiceValue = $worksheet->getCell("F".$key)->getValue();
                $TaxValue = $worksheet->getCell("J".$key)->getValue();
                $supplier = $worksheet->getCell("G".$key)->getValue();

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
        foreach ($worksheet1->getRowIterator() as $row) {
            $rowIndex = $row->getRowIndex();
            $cellIterator = $row->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(false);
            $columnLetter = 'a';
            $columnIndex = 1;
            foreach ($cellIterator as $cell) {
                if($rowIndex > 1){
                    $columnValue = $worksheet1->getCell($columnLetter.$columnIndex)->getValue();
                    $excelsheet[$rowIndex][$columnValue] = $cell->getCalculatedValue();
                    $columnLetter++;  
                }
               
            }   
        }

        $worksheet2 = $spreadsheet2->getActiveSheet();
        foreach ($worksheet2->getRowIterator() as $row2) {
            $rowIndex2 = $row2->getRowIndex();
            $cellIterator2 = $row2->getCellIterator();
            $cellIterator2->setIterateOnlyExistingCells(false);
            $columnLetter2 = 'a';
            $columnIndex2 = 1;
            foreach ($cellIterator2 as $cell2) {
                if($rowIndex2 > 1){
                    $columnValue2 = $worksheet2->getCell($columnLetter2.$columnIndex2)->getValue();
                    $excelsheet2[$rowIndex2][$columnValue2] = $cell2->getCalculatedValue();
                    $columnLetter2++;  
                }
               
            }   
        }




        echo "<pre>";
        print_r($excelsheet);
        print_r($excelsheet);

        return $resultDate;

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

