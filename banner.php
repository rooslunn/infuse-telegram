<?php
/**
 * Created by PhpStorm.
 * User: russ
 * Date: 2/11/17
 * Time: 9:26 PM
 */

declare(strict_types=1);

/*
 * Class declarations
 */


interface VisitorStoreInterface {
    public function save(string $ip_address, string $user_agent, string $page_url);
}

class MySqlVisitorStore implements VisitorStoreInterface {

    protected const DSN = 'mysql:dbname=infuse;host=127.0.0.1';
    protected const USER = 'infuse';
    protected const PASSWORD = 'infuse';
    protected const TABLE = 'banner_trace';

    protected function get_dbh()
    {
        try {
            $dbh = new PDO(self::DSN, self::USER, self::PASSWORD);
        } catch (PDOException $e) {
            $dbh = null;
        }

        return $dbh;
    }

    protected function insert_or_update(string $ip_address, string $user_agent, string $page_url):bool
    {
        $dbh = $this->get_dbh();
        if ($dbh === null) {
            return false;
        }

        $sql = '
            INSERT INTO ' . self::TABLE . ' (ip_address, user_agent, page_url, views_count) 
            VALUES (:ip_address, :user_agent, :page_url, :views_count)
            ON DUPLICATE KEY UPDATE views_count = views_count + 1';


        $stmt = $dbh->prepare($sql);
        $stmt->bindValue(':ip_address', "{$ip_address}");
        $stmt->bindValue(':user_agent', "{$user_agent}");
        $stmt->bindValue(':page_url', "{$page_url}");
        $stmt->bindValue(':views_count', 1);

        return $stmt->execute();
    }

    public function save(string $ip_address, string $user_agent, string $page_url):bool
    {
        $store = new static();
        return $store->insert_or_update($ip_address, $user_agent, $page_url);
    }
}

class BannerTraceApp {

    protected const SUCCESS_IMAGE = 'image/banner.png';
    protected const DB_ERROR_IMAGE = 'image/database-error.png';

    protected $store;

    public function __construct(VisitorStoreInterface $store)
    {
        $this->store = $store;
    }

    protected function getIp():string
    {
        return $_SERVER['REMOTE_ADDR'];
    }

    protected function getUserAgent():string
    {
        return $_SERVER['HTTP_USER_AGENT'];
    }

    protected function getPageUrl():string
    {
        return $_SERVER['HTTP_REFERER'];
    }

    public function run()
    {
        $ip_address = $this->getIp();
        $user_agent = $this->getUserAgent();
        $page_url = $this->getPageUrl();

        header('Content-Type: image/png');
        if (!$this->store->save($ip_address, $user_agent, $page_url)) {
            readfile(self::DB_ERROR_IMAGE);
        }
        readfile(self::SUCCESS_IMAGE);
    }
}

/*
 * App
 */
$app = new BannerTraceApp(new MySqlVisitorStore());
$app->run();
