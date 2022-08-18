<?php
    $defo = "";
    $user = $defo;
    $password = $defo;
    if (!(empty($_GET["user"]) || empty($_GET["password"]))){
        $user = $_GET["user"];
        $password = $_GET["password"];
    }


    if (isset($_GET["size"])){
        $size = intval((string)$_GET["size"]);
    }
    if (!isset($size) || $size < 1){
        $size = 5;
    }

    if (isset($_GET["page"])){
        $page = intval((string)$_GET["page"]);
    } else{
        $page = 0;
    }

    if ($page < 0)
    {
        $page = 0;
    }


    if (isset($_GET["keywords"])){
    // 引用 serch https://qiita.com/mpyw/items/a704cb900dfda0fc0331
        /*
        function extractKeywords(string $input, int $limit = -1): array
        {
            return array_values(array_unique(preg_split('/[\p{Z}\p{Cc}]++/u', $input, $limit, PREG_SPLIT_NO_EMPTY)));
        }
        */
        $searchword = array_values(array_unique(preg_split('/[\p{Z}\p{Cc}%_]++/u', $_GET["keywords"], -1, PREG_SPLIT_NO_EMPTY)));
    } else {
        $searchword = [];
    }
        $keywords = array_map(function($txt){return "%". $txt ."%";}, $searchword);
    try {
        // データベースに接続
        $pdo = new PDO(
            // ホスト名、データベース名
            'mysql:host=localhost;dbname=order;charset=utf8',
            // ユーザー名
            'root',
            // パスワード
            '',
            // レコード列名をキーとして取得させる
            [ PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC ]
        );


    // SQL文作成
    // user 特定
        // SQLquery作成
        $query = 'SELECT * FROM user WHERE user_id = :user_id AND password = :password';

        // SQL文をセット
        $stmt = $pdo->prepare($query);

        // バインド
        $stmt->bindParam(':user_id', $user);
        $stmt->bindParam(':password', $password);

        // SQL文を実行
        $stmt->execute();
        $lenkeyword = count($keywords);
        // 実行結果のフェッチ
        $result = $stmt->fetchAll();
        if (!empty($result))
        {


    // 検索ワード用SQL条件式生成
            $tmp = "";
            for ($i = 0; $i < $lenkeyword; $i++){
                $tmp = $tmp." AND NAME LIKE :word".strval($i);
            }
            $qwhere = $tmp;




    // ユーザのデータ登録件数
            $user_name = $result[0]["NAME"];
            $user_id = $result[0]["USER_ID"];
            $query = "SELECT COUNT(*) as ct FROM products WHERE TRUE".$qwhere; // ORDER_USER = :user
            $stmt = $pdo->prepare($query);
            //$stmt->bindParam(':user', $user_id);

            for ($i = 0; $i < $lenkeyword; $i++){
                $stmt->bindParam(":word".strval($i), $keywords[$i]);
            }

            //$stmt->bindParam(':size', $size, PDO::PARAM_INT); COUNT(*)/:size as tPages
            $stmt->execute();

            $result = $stmt->fetchAll()[0];

            $maxSize = $result["ct"];
        
    
            // 最大ページ最小ページ計算 ページ調整
            if ($page < 0){
                $page = 0;
            }
            // ページ修正
            $nextFlag = ($page < (($maxSize-($maxSize%$size))/$size));
            if ($nextFlag){
                $nextpage = "true";
            }else{
                $page = (($maxSize-($maxSize%$size))/$size);
                $nextpage = "false";
            }

            // ページスタートレコード
            $start = $page * $size;

            
            // SQL文作成

            $query = "SELECT user.NAME AS USERNAME, p.PRODUCT_ID AS ID, type.NAME AS TYPE, p.NAME as NAME, PRICE, ORDER_DATE, DELIVERY_DATE, status.status AS STATUS".
                     " FROM (".
                     " SELECT * FROM products WHERE TRUE".$qwhere.//ORDER_USER = :user
                     " ORDER BY ORDER_DATE ASC, PRODUCT_ID ASC LIMIT :i,:j".
                     " ) as p".
                     " Inner Join type on type.type_id = p.type".
                     " Inner Join status on status.STATUS_ID = p.ORDER_STATUS".
                     " Inner Join user on user.USER_ID = p.ORDER_USER".
                     " ORDER BY ORDER_DATE ASC, DELIVERY_DATE DESC, PRODUCT_ID ASC";

            // データ取り出し
            $stmt = $pdo->prepare($query);
            //$stmt->bindParam(':user', $user_id);
            $stmt->bindParam(':j', $size , PDO::PARAM_INT);
            $stmt->bindParam(':i', $start, PDO::PARAM_INT);
            for ($i = 0; $i < $lenkeyword; $i++){
                $stmt->bindParam(":word".strval($i), $keywords[$i]);
            }
            $stmt->execute();
        
            $product =$stmt->fetchAll();

            // jsonデータ送信
            $property = '{"maxvalue":'.$maxSize.',"page":'.$page.',"nextpage":'.$nextpage.',"keywords":'.json_encode($searchword).'}';
            $key      = '{"TYPE":"種類","NAME":"商品名","PRICE":"料金","ORDER_DATE":"注文日","DELIVERY_DATE":"納品日","STATUS":"ステータス","USERNAME":"注文者"}';
            $type     = '{"version":1.1,"locate":"ja-jp","currency":{"head":"JPY","foot":"円"},"property":'.$property.'}';

            $output_json= json_decode('{"type":'.$type.', "key":'.$key.', "values":'.json_encode($product, JSON_UNESCAPED_UNICODE)."}");
            echo json_encode($output_json, JSON_UNESCAPED_UNICODE);


        }
        else
        {
            exit();
        }





    } catch (PDOException $e) {
        //例外発生したら無視
        //require_once 'exception_tpl.php';
        echo $e->getMessage();
        exit();
    } catch (Exception $e) {echo 504;exit();}
?>