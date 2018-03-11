var fs = require('fs');
var moment = require('moment');

(function() {

    let helpText = `Example: node routine.js 2017-01-01 2017-02-02
         Where 2017-01-01 is queryDate and 2017-02-02 is showDate`;

      if ((['h', '-h', '--h', 'help', '-help', '--help'].indexOf(process.argv[2]) !== -1) || 
          (process.argv.length <= 3)) {
          console.log(helpText);
          return true;
      } else if ((!process.argv[3].match(/\d{4}-\d{2}-\d{2}/g)) || 
                (!process.argv[2].match(/\d{4}-\d{2}-\d{2}/g))){
          console.log('invalid date');
          return;
      }
        const open_for_sale = "Open for sale";
        const sale_not_started = "Sale not started";
        const sold_out = "Sold out";
        const in_the_past = "In the past";
        let queryDate = moment(process.argv[2], 'YYYY-MM-DD');
        let showDate = moment(process.argv[3], 'YYYY-MM-DD');      
        let output = [];
        let name;
        let date;
        let genre;
        let status;
        let daysBetween;
        let tleft;
        let tavailable;
        let dateBigHall;
        let dateSmallHall;
        let dateSale;
        let inventory;
        let finalOutput = [];
        let genrePivot;
        let showList = [];


            
        fs.readFile('data/shows.csv', 'utf8', function(err, contents) {
        
        const shows = contents.split('\r\n');
        shows.map((r) => {
            // date is always in the middle, so we use it as a separator
            if (date = r.match(/\d{4}-\d{2}-\d{2}/g)) { 
                name = r.split(date)[0]         // at the left side of date is the name 
                        .trim()                 // remove white spaces
                        .replace(/,$/g,'')      // remove the last comma
                        .replace(/\"/g,'');     // remove doublequotes
                genre = r.split(date)[1]       // at the right side of date is the genre
                          .trim()               // remove white spaces
                          .replace(/^,/g,'')    // remove previous comma
                          .replace(/\"/g,'');   // remove double quotes
            }

            if (moment(date, 'YYYY-MM-DD').format('YYYY-MM-DD') == moment(showDate, 'YYYY-MM-DD').format('YYYY-MM-DD')) { // show date matches a show
                console.log(name);
                dateBigHall = showDate; // state dates for type of treatment
                dateSmallHall = moment(showDate).add(60, 'days');
                dateSale = moment(showDate).add(80, 'days');

                if (dateBigHall.diff(queryDate, 'days') > 0) {   // here we treat bigHall 
                    daysBetween = dateBigHall.diff(queryDate, 'days');
                    status = getStatus(daysBetween);

                    if (status === open_for_sale) {
                        tleft = (daysBetween - 5) * 10;
                        tavailable = (tleft < 200) ? 10 : 0;
                    } else {
                        tleft = 200;
                        tavailable = 0;
                    }
                } else if(dateSmallHall.diff(queryDate, 'days') > 0) { // smallHall full price
                    daysBetween = dateSmallHall.diff(queryDate, 'days');
                    status = getStatus(daysBetween);
                    if (status === open_for_sale) {
                        tleft = (daysBetween - 5) * 5;
                        tavailable = (tleft < 100) ? 5 : 0;
                    } else {
                        tleft = 100;
                        tavailable = 0;
                    }
                } else if (dateSale.diff(queryDate, 'days') > 0) { // smallHall discount
                    daysBetween = dateSale.diff(queryDate, 'days');
                    status = getStatus(daysBetween);
                    if (status === open_for_sale) {
                        tleft = (daysBetween - 5) * 5;
                        tavailable = (tleft < 100) ? 5 : 0;
                    } else {
                        tleft = 100;
                        tavailable = 0;
                    }
                } else {
                    status = in_the_past;
                    tleft = 0;
                    tavailable = 0;
                }         

                if (status === sold_out) { // this should be refactored I know :)
                    tleft = 0;
                }

                output.push({name, genre, status, tleft, tavailable});
            }
        });


        sortGenre(output);
        
        if (output.length < 1) {
            console.log('No shows for those dates.');
            return;
        }

        genrePivot = output[0].genre;
        
        output.map(function(o) {
            if (o.genre === genrePivot) {
                showList.push({"title":o.name, 
                "tickets left": o.tleft, 
                "tickets available": o.tavailable, 
                "status": o.status });
            } else {
                finalOutput.push({"genre": genrePivot, "shows": showList});
                showList = [];
                genrePivot = o.genre;
                showList.push({"title":o.name, 
                "tickets left": o.tleft, 
                "tickets available": o.tavailable, 
                "status": o.status });
            }
        });
        finalOutput.push({"genre": genrePivot, "shows": showList});
        inventory = JSON.stringify({"inventory": finalOutput});


        fs.writeFile('data/storage.json', inventory, (err) => {
            if (err) throw err;
            console.log('The file data/storage.json has been saved!');
            console.log(inventory);
          });
    });

    function getStatus(daysBetween) {
        let status;
        if (daysBetween < 25 && daysBetween > 5) {
            status = open_for_sale;    
        } else if (daysBetween > 25) {
            status = sale_not_started;
        } else if (daysBetween <= 5 && daysBetween > 0) {
            status = sold_out;
        } else {
            status = in_the_past;
        }
        return status;
    }

    function sortGenre(output) {
        // sort by genre
        output.sort(function(a, b) {
            var genreA = a.genre.toUpperCase(); // ignore upper and lowercase
            var genreB = b.genre.toUpperCase(); // ignore upper and lowercase
            if (genreA < genreB) {
            return -1;
            }
            if (genreA > genreB) {
            return 1;
            }    
            // names must be equal
            return 0;
        });        
    }

})();
