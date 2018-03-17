<?php
require 'vendor/autoload.php';

use Carbon\Carbon;

const OPEN_FOR_SALE = "Open for sale";
const SALE_NOT_STARTED = "Sale not started";
const SOLD_OUT = "Sold out";
const IN_THE_PAST = "In the past";

class showInventory 
{
    private $shows = [];
    private $fileName = "";
    private $paramQueryDate = "";
    private $paramShowDate = "";

    private $showsAtparamShowDate = [];
    private $sortedFinalOutput = [];

    private $genrePivot = "";
    private $showList = [];

    /**
     * load into properties information from parameters.
     * 
     * @param {array} array of three positions: [fileName, queryDate, showDate].
     * 
     * @return {object} fully loaded with information from file, or die in error.
     */
    public function load($arguments) 
    {        
        $this->validateAndLoadParams($arguments);

        $this->shows = file($this->fileName, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        

        $parseShowsInformation = function($s) {
            preg_match('/\d{4}-\d{2}-\d{2}/', $s, $output);
            try {
                $show['dateBigHall'] = Carbon::createFromFormat('Y-m-d', $output[0]);                
                $show['dateSmallHall'] = Carbon::createFromFormat('Y-m-d', $output[0])->addDays(60);
                $show['dateSale'] = Carbon::createFromFormat('Y-m-d', $output[0])->addDays(80);
                preg_match('/\".*\"\,/', $s, $output);
                $show['title'] = rtrim($output[0], '",');
                $show['title'] = ltrim($show['title'], '"');
                
                preg_match('/\,\".*\"/', $s, $output);
                $show['genre'] = rtrim($output[0], '"');
                $show['genre'] = ltrim($show['genre'], ',"');

            } catch (Exception $err)  {
                return "invalid-data";
            }            
            
            return $show;
        };
        
        $this->shows = array_map($parseShowsInformation, $this->shows);

        return $this;
    }

    
    /**
     * validateAndLoadParams validate params and saves them in properties.
     * 
     * @param {array} array of three positions: [fileName, queryDate, showDate].
     * 
     * @return {object} true having set up params or die on error.
     */
    private function validateAndLoadParams($arguments)
    {
        if (file_exists($arguments[1])===true)
            $this->fileName = $arguments[1];
        else
            die("wrong file");

        try {
            $this->paramQueryDate = Carbon::createFromFormat('Y-m-d', $arguments[2]);
        } catch (Exception $err) {
            echo "wrong query date: ";
            die($err->getMessage());
        }

        try {
            $this->paramShowDate = Carbon::createFromFormat('Y-m-d', $arguments[3]);
        } catch (Exception $err) {
            echo "wrong show date: ";
            die($err->getMessage());
        }
                
        return true;
    }


    /**
     * getShowsAtDate filter shows from file into property array with information 
     *  about shows matching date with paramShowDate.
     * 
     * @return {object} with property showsAtparamShowDate, array containing matching shows.
     */
    public function getShowsAtDate() 
    {
        $this->showsAtparamShowDate = [];

        $setTicketsAndStatus = function($s){
            if ($s['dateBigHall'] == $this->paramShowDate) {
                if ($s['dateBigHall']->diffInDays($this->paramQueryDate) > 0) {
                    $daysBetween = $s['dateBigHall']->diffInDays($this->paramQueryDate);
                    $s['status'] = $this->getStatus($daysBetween);
                    if ($s['status'] === OPEN_FOR_SALE) {
                        $s['tleft'] = ($daysBetween - 5) * 10;
                        $s['tavailable'] = ($s['tleft'] < 200) ? 10 : 0;
                    } else {
                        $s['tleft'] = 200;
                        $s['tavailable'] = 0;
                    }
                } elseif ($s['dateSmallHall']->diffInDays($this->paramQueryDate) > 0) {
                    $daysBetween = $s['dateSmallHall']->diffInDays($this->paramQueryDate);
                    $s['status'] = $this->getStatus($daysBetween);
                    if ($s['status'] === OPEN_FOR_SALE) {
                        $s['tleft'] = ($daysBetween - 5) * 5;
                        $s['tavailable'] = ($s['tleft'] < 100) ? 5 : 0;
                    } else {
                        $s['tleft'] = 100;
                        $s['tavailable'] = 0;
                    }
                } elseif ($s['dateSale']->diffInDays($this->paramQueryDate) > 0) {
                    $daysBetween = $s['dateSale']->diffInDays($this->paramQueryDate);
                    $s['status'] = $this->getStatus($daysBetween);
                    if ($s['status'] === OPEN_FOR_SALE) {
                        $s['tleft'] = ($daysBetween - 5) * 5;
                        $s['tavailable'] = ($s['tleft'] < 100) ? 5 : 0;
                    } else {
                        $s['tleft'] = 100;
                        $s['tavailable'] = 0;
                    }
                }else {
                    $daysBetween = $s['dateSale']->diffInDays($this->paramQueryDate);
                    $s['status'] = $this->getStatus($daysBetween);
                        $s['tleftSale'] = 0;
                        $s['tavailable'] = 0;
                }   
                array_push($this->showsAtparamShowDate, $s);
            }

        };

        array_map($setTicketsAndStatus,$this->shows);

        usort($this->showsAtparamShowDate, function($a, $b) {
            return $a['genre'] <=> $b['genre'];
        });

        return $this;
    }

    /**
     * parseOutputObject matching dates in expected order, with tickets information and status.
     * 
     * @return {object} with property sortedFinalOutput, array containing matching shows
     *  just tickets and status information sorted and grouped.
     */
    public function parseOutputObject() {
        if (count($this->showsAtparamShowDate) > 0) {
            $this->genrePivot = $this->showsAtparamShowDate[0]['genre'];        
            $this->showList = [];
            
            $parseOutputInformation = function($s)
            {
                if ($s['genre'] === $this->genrePivot) {
                    array_push($this->showList, Array("title" => $s['title'], 
                    "tickets left" => $s['tleft'], 
                    "tickets available" => $s['tavailable'], 
                    "status" => $s['status']));
                } else {
                    array_push($this->sortedFinalOutput, Array("genre" => $this->genrePivot, "shows" => $this->showList));                
                    $this->showList = [];
                    $this->genrePivot = $s['genre'];
                    array_push($this->showList, Array("title" => $s['title'], 
                    "tickets left" => $s['tleft'], 
                    "tickets available" => $s['tavailable'], 
                    "status" => $s['status']));
                }
            };                
            array_map($parseOutputInformation, $this->showsAtparamShowDate);
            array_push($this->sortedFinalOutput, Array("genre" => $this->genrePivot, "shows" => $this->showList));                           

            return $this;
        }
    }

    
    /**
     * getStatus translate days between show into status string.
     * 
     * @return {string} with status.
     */
    public function getStatus($daysBetween) {
        $status = "";
        if ($daysBetween <= 25 && $daysBetween > 5) {
            $status = OPEN_FOR_SALE;    
        } else if ($daysBetween > 25) {
            $status = SALE_NOT_STARTED;
        } else if ($daysBetween <= 5 && $daysBetween > 0) {
            $status = SOLD_OUT;
        } else {
            $status = IN_THE_PAST;
        }
        return $status;
    }

    
    /**
     * printJsonInventory pretty prints json.
     * 
     * @return {boolean} true.
     */
    public function printJsonInventory()
    {
        echo json_encode(Array("inventory" => $this->sortedFinalOutput), JSON_PRETTY_PRINT);

        return true;
    }

    
    /**
     * return array.
     * 
     * @return {array}.
     */
    public function getArrayGenres()
    {
        return json_encode($this->sortedFinalOutput, JSON_PRETTY_PRINT);        
    }    
}

if (isset($argv)) {
    $inventory = new showInventory;
    $inventory->load($argv);
    $inventory->getShowsAtDate()->parseOutputObject()->printJsonInventory();
} 

?>
