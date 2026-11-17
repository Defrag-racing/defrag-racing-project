<?php

namespace App\External;

class Q3DFLastRecordsApi extends Q3DFRecordsApi {
    public $url = "https://q3df.org/api/getLastRecords";

    public $timeout = 30;
}
