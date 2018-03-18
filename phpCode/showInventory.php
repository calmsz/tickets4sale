<?php
require 'vendor/autoload.php';

use Carbon\Carbon;

const OPEN_FOR_SALE = "Open for sale";
const SALE_NOT_STARTED = "Sale not started";
const SOLD_OUT = "Sold out";
const IN_THE_PAST = "In the past";
const PRICE_COMEDY = 50;
const PRICE_DRAMA = 40;
const PRICE_MUSICAL = 70;

class showInventory 
{
    private $shows = [];
    private $fileName = "";
    private $paramQueryDate = "";
    private $paramShowDate = "";
    private $paramShowDateMinus25 = "";
    private $paramShowDateMinus5 = "";
    private $paramDaysBetween = 0;

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
                $show['lastShow'] = Carbon::createFromFormat('Y-m-d', $output[0])->addDays(100);
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
            $this->paramShowDateMinus25 = Carbon::createFromFormat('Y-m-d', $arguments[3])->subDays(25);
            $this->paramShowDateMinus5 = Carbon::createFromFormat('Y-m-d', $arguments[3])->subDays(5);
        } catch (Exception $err) {
            echo "wrong show date: ";
            die($err->getMessage());
        }        

        try {
            $this->paramDaysBetween = $this->paramShowDateMinus5->diffInDays($this->paramQueryDate);
        } catch (Exception $err) {
            echo "Problem calculating days between param dates: ";
            die($err->getMessage());
        }
        $this->status = $this->getStatus();
                
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
            if ($s['genre']=="COMEDY") {
                $price = PRICE_COMEDY;
            } elseif ($s['genre']=="MUSICAL") {
                $price = PRICE_MUSICAL;
            } else {
                $price = PRICE_DRAMA;
            }
            
            if (($this->paramShowDate->greaterThanOrEqualTo($s['dateBigHall'])) &&
                ($this->paramShowDate->lessThanOrEqualTo($s['lastShow']))) {
                
                    if ($this->paramShowDate->lessThan($s['dateSmallHall'])) {
                        /**
                         * 200 capacity, 10xd, price full
                        */
                        $capacity = 200;
                        $ticketsPerDay = 10;                    
                    } elseif (($this->paramShowDate->greaterThanOrEqualTo($s['dateSmallHall'])) &&
                        ($this->paramShowDate->lessThan($s['dateSale']))) {
                        /**
                         * 100 capacity, 5xd, price full
                         */
                        $capacity = 100;
                        $ticketsPerDay = 5;
                    } elseif ($this->paramShowDate->greaterThanOrEqualTo($s['dateSale'])) {
                        /**
                         * 100 capacity, 5xd, 0.8 times price
                         */
                        $capacity = 100;
                        $ticketsPerDay = 5;
                        $price = $price * 0.8;
                    }

                    $s['status'] = $this->status;
                    $s['price'] = $price;
                    if ($s['status'] == OPEN_FOR_SALE) {
                        $s['tleft'] = (($this->paramDaysBetween) * $ticketsPerDay) + $ticketsPerDay;
                        $s['tavailable'] = ($s['tleft'] < $capacity) ? $ticketsPerDay : 0;                     
                    } elseif ($s['status'] == SALE_NOT_STARTED) {
                        $s['tleft'] = $capacity;
                        $s['tavailable'] = 0;   
                    } else {
                        $s['tleft'] = 0;
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
                    "status" => $s['status'],
                    "price" => $s['price']));
                } else {
                    array_push($this->sortedFinalOutput, Array("genre" => $this->genrePivot, "shows" => $this->showList));                
                    $this->showList = [];
                    $this->genrePivot = $s['genre'];
                    array_push($this->showList, Array("title" => $s['title'], 
                    "tickets left" => $s['tleft'], 
                    "tickets available" => $s['tavailable'], 
                    "status" => $s['status'],
                    "price" => $s['price']));
                }
            };                
            array_map($parseOutputInformation, $this->showsAtparamShowDate);
            array_push($this->sortedFinalOutput, Array("genre" => $this->genrePivot, "shows" => $this->showList));                           

        }
        return $this;
    }

    
    /**
     * getStatus translate days between show into status string.
     * 
     * @return {string} with status.
     */
    public function getStatus() {
        $status = "";
        if ($this->paramQueryDate->lessThan($this->paramShowDateMinus25)) {
            $status = SALE_NOT_STARTED;
        } elseif ($this->paramQueryDate->greaterThanOrEqualTo($this->paramShowDateMinus25) &&
            ($this->paramQueryDate->lessThan($this->paramShowDateMinus5))
            ) {
            $status = OPEN_FOR_SALE;    
        } elseif ($this->paramQueryDate->greaterThanOrEqualTo($this->paramShowDateMinus5) &&
            $this->paramQueryDate->lessThanOrEqualTo($this->paramShowDate)) {
            $status = SOLD_OUT;
        } elseif ($this->paramQueryDate->greaterThan($this->paramShowDate)) {
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
        if (count($this->sortedFinalOutput) > 0) {
            echo json_encode(Array("inventory" => $this->sortedFinalOutput), JSON_PRETTY_PRINT);
        } else {
            echo "No shows for showDate=$this->paramShowDate and queryDate=$this->paramQueryDate";
        }

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
