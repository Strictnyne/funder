<?php
// service schema for EOS connections
// note the hidden=true flag prevents this from appearing in the editor
// since the connections will be auto-generated by eos-mc
return [
    "Connections" => ["type" =>"group","hidden"=>true,"fields"=>[
        "outbound" => ["type"=>"multigroup","extensible"=>true,"fields"=> [
            "serviceName" => ["type"=>"text","sample"=>"Check Processor"],
            "serviceUrl" => ["type"=>"text","sample"=>"http://path.to.service"],
            "authentication" => ["type"=>"enum","valid"=>["oauth","apikey","none"]],
            "clientid"=>["type"=>"text","sample"=>"20"],
            "clientsecret"=>["type"=>"text","sample"=>"x35Y33Ab..."],
            "apikey"=>["type"=>"text","sample"=>"t5E3Wx..."],
            "apisecret"=>["type"=>"text","sample"=>"GG4rEw2X..."],
            "apiversion"=>["type"=>"text","sample"=>"1"]
            ]
        ]
    ]]
];
