<?php

return [

    // mysql config
    "MysqlStorageClient" => [
        "host" => "localhost",
        "dbuser" => "shop",
        "passwd" => "",
        "dbname" => "shop",
        "port" => 3306,
        "timeout" => 15,
        "prefix" => "shop_",
    ],

    // redis config
    "RedisStorageClient" => [
        "host" => "localhost",
        "port" => 6379,
        "serialize" => 1,
        "auth" => "",
        "prefix" => "",
        "timeout" => 15,
    ],

    // mongoDB config
    "MongodbStorageClient" => [
        "username" => "",
        "password" => "",
        "connectTimeoutMS" => 60,
        "uri" => "mongodb://localhost:27017/shop",
        "dbname" => "shop",
    ],
];