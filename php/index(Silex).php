<?php
require_once __DIR__.'/../vendor/autoload.php';
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

Request::enableHttpMethodParameterOverride();

$app = new Silex\Application();
$app['debug'] = true;
$app->register(new Silex\Provider\TwigServiceProvider(),
        ['twig.path' => __DIR__ . '/../view']);
$app->register(new Silex\Provider\DoctrineServiceProvider(),
        ['db.options' => ['driver' => 'pdo_mysql', 'dbname' => 'equipment_db', 'charset' => 'utf8']]);
//Информация для таблицы на главной		
$app->get('/', function () use ($app) {
		$conn = $app['db'];
		$objects = $conn->fetchAll('SELECT * 
									FROM object ob 
									LEFT JOIN type t ON t.pk_type = ob.fk_type 
									LEFT JOIN place pl ON pl.pk_place = ob.fk_place 
									LEFT JOIN vsp v ON v.pk_vsp = pl.fk_vsp');
									
		$types = $conn->fetchAll('SELECT * FROM  type');
		$vsps = $conn->fetchAll('SELECT * FROM  vsp');
		$fios = $conn->fetchAll('SELECT * FROM  fio');
		$status = $conn->fetchAll('SELECT * FROM  status');
		$places = $conn->fetchAll('SELECT * FROM  place');
		
		return $app['twig']->render('Equipment.twig',['objects'=>$objects, 'types'=>$types, 'vsps'=>$vsps, 'fios'=>$fios,'places'=>$places,'status'=>$status]);
});

//Информация о объекте для 1-го pill	
$app->get('/get_obj_info/{id}', function ($id) use ($app) {
    $conn = $app['db'];
    $object = $conn->fetchAssoc('SELECT * 
									FROM object ob 
									LEFT JOIN type t ON t.pk_type = ob.fk_type 
									LEFT JOIN place pl ON pl.pk_place = ob.fk_place 
									LEFT JOIN fio f ON f.pk_fio = pl.fk_fio 
									LEFT JOIN vsp v ON v.pk_vsp = pl.fk_vsp 
									LEFT JOIN status st ON st.pk_status = ob.fk_status
									WHERE ob.pk_object = ?', [$id]);
	
	return $app->json(array('object'=>$object), 200);
});

 //Информация о ремонте объекта для 2-го pill
$app->get('/get_obj_rep/{id}', function ($id) use ($app) {
    $conn = $app['db'];
    $object = $conn->fetchAll('SELECT * 
								FROM repair 
								WHERE fk_object = ?', [$id]);

    return $app->json(array('object'=>$object), 200);
});

//Информация о перемещении объекта для 3-го pill
$app->get('/get_obj_his/{id}', function ($id) use ($app) {
    $conn = $app['db'];
    $object = $conn->fetchAll('SELECT *, 
									pl1.room AS room_from, 
									pl2.room AS room_to, 
									v1.name_vsp AS name_vsp_from, 
									v2.name_vsp AS name_vsp_to, 
									f1.surname AS surname_from, 
									f2.surname AS surname_to, 
									f1.fio_name AS fio_name_from, 
									f2.fio_name AS fio_name_to, 
									f1.patronymic AS patronymic_from, 
									f2.patronymic AS patronymic_to 
									FROM place_history ph 
									LEFT JOIN place pl1 ON pl1.pk_place = ph.fk_place_from 
									LEFT JOIN place pl2 ON pl2.pk_place = ph.fk_place_to 
									LEFT JOIN vsp v1 ON v1.pk_vsp = pl1.fk_vsp 
									LEFT JOIN vsp v2 ON v2.pk_vsp = pl2.fk_vsp 
									LEFT JOIN fio f1 ON f1.pk_fio = pl1.fk_fio 
									LEFT JOIN fio f2 ON f2.pk_fio = pl2.fk_fio 
									WHERE ph.fk_object = ?', [$id]);
								 
    return $app->json(array('object'=>$object), 200);
});

//Информация о паспортах объекта для 4-го pill
$app->get('/get_obj_pas/{id}', function ($id) use ($app) {
    $conn = $app['db'];
    $object = $conn->fetchAll('SELECT *
								FROM pasport_history
								WHERE fk_object = ?', [$id]);
								 
    return $app->json(array('object'=>$object), 200);
});

//Информация о статусах объекта для 5-го pill
$app->get('/get_obj_sta/{id}', function ($id) use ($app) {
    $conn = $app['db'];			
	$object = $conn->fetchAll('SELECT * 
								FROM status_history sh
								LEFT JOIN status st ON st.pk_status = sh.fk_status
								WHERE sh.fk_object = ?', [$id]);
    return $app->json(array('object'=>$object), 200);
});

//Добавление нового объекта
$app->post('/new_object/', function (Request $req) use ($app) {
		$conn = $app['db'];
		$name = $req->get('name');
		$type = $req->get('type');
		$date = $req->get('date');
		$factory_number = $req->get('factory_number');
		$inventory_number = $req->get('inventory_number');
		$vsp = $req->get('vsp');
		$room = $req->get('room');
		$fio = $req->get('fio');
		$state = $req->get('state');
		
		$now = date('Y-m-d H-i-s');
		
		foreach ($_FILES as $file) {
		$path = "images/pasport/".$now." ".$file['name'];
		move_uploaded_file($file['tmp_name'], $path);}
						
		$conn->insert('object', ['name' => $name, 'fk_type'=>$type,
								'date_buy' => $date, 'factory_number'=>$factory_number, 'inventory_number'=>$inventory_number,
								'fk_place' => 1,								
								'pasport' => $path, 'fk_status'=>$state ]);
		
		$id = $conn->fetchAssoc('SELECT pk_object FROM object ORDER BY pk_object DESC LIMIT 1');
		
		$object = $conn->fetchAssoc('SELECT * 
									FROM object ob 
									LEFT JOIN type t ON t.pk_type = ob.fk_type 
									LEFT JOIN place pl ON pl.pk_place = ob.fk_place 
									LEFT JOIN vsp v ON v.pk_vsp = pl.fk_vsp
									WHERE ob.pk_object = ?', [$id['pk_object']] );
		
		return $app->json(array('object'=>$object), 200);
});

//Изменение местоположения объекта
$app->put('/new_place/{id}', function (Request $req, $id) use ($app) {
		$conn = $app['db'];
		$vsp = $req->get('vsp');
		$room = $req->get('room');
		$state = $req->get('state');
		$date = $req->get('date');
		$fio = $req->get('fio');
		
		$conn->insert('place', ['fk_vsp' => $vsp, 'room'=>$room, 'fk_fio'=>$fio]);	
		
		$place = $conn->fetchAssoc('SELECT pk_place FROM place ORDER BY pk_place DESC LIMIT 1');
		
		$object = $conn->fetchAssoc('SELECT * FROM  object WHERE pk_object = ?', [$id]);
		
		$object_place = $object['fk_place'];
		
		$conn->insert('place_history', ['fk_object' => $id, 'fk_place_to'=>$place['pk_place'], 'date_move'=> $date, 'fk_place_from'=> $object_place]);			

		$conn->executeUpdate('UPDATE object SET fk_place=?, fk_status=? WHERE pk_object = ?',[$place['pk_place'], $state, $id]);
		
		$object_1 = $conn->fetchAssoc('SELECT * 
									FROM object ob 
									LEFT JOIN type t ON t.pk_type = ob.fk_type 
									LEFT JOIN place pl ON pl.pk_place = ob.fk_place 
									LEFT JOIN fio f ON f.pk_fio = pl.fk_fio 
									LEFT JOIN vsp v ON v.pk_vsp = pl.fk_vsp 
									LEFT JOIN status st ON st.pk_status = ob.fk_status
									WHERE ob.pk_object = ?', [$id]);
						
		
		return $app->json(array('object'=>$object_1), 200);
});

//Ремонт объекта
$app->put('/new_repair/{id}', function (Request $req, $id) use ($app) {
		$conn = $app['db'];
		$date = $req->get('date');
		$cause = $req->get('cause');
		$cost = $req->get('cost');

		
		$conn->insert('repair', ['fk_object' => $id, 'date_repair'=>$date, 'cost'=>$cost, 'text_cause'=>$cause]);			
		
		$object_1 = $conn->fetchAssoc('SELECT * 
									FROM object ob 
									LEFT JOIN type t ON t.pk_type = ob.fk_type 
									LEFT JOIN place pl ON pl.pk_place = ob.fk_place 
									LEFT JOIN fio f ON f.pk_fio = pl.fk_fio 
									LEFT JOIN vsp v ON v.pk_vsp = pl.fk_vsp 
									LEFT JOIN status st ON st.pk_status = ob.fk_status
									WHERE ob.pk_object = ?', [$id]);
						
		
		return $app->json(array('object'=>$object_1), 200);
});

//Списание объекта
$app->put('/new_write_off/{id}', function (Request $req, $id) use ($app) {
		$conn = $app['db'];
		$state = $req->get('state');
		$date = $req->get('date');
		$act = $req->get('act');
		
		$conn->insert('status_history', ['date_status_history' => $date, 'act_status_history'=>$act, 'fk_status'=>$state, 'fk_object'=>$id]);		
		
		$conn->executeUpdate('UPDATE object SET fk_status=? WHERE pk_object = ?',[$state, $id]);		
		
		
		
		$object_1 = $conn->fetchAssoc('SELECT * 
									FROM object ob 
									LEFT JOIN type t ON t.pk_type = ob.fk_type 
									LEFT JOIN place pl ON pl.pk_place = ob.fk_place 
									LEFT JOIN fio f ON f.pk_fio = pl.fk_fio 
									LEFT JOIN vsp v ON v.pk_vsp = pl.fk_vsp 
									LEFT JOIN status st ON st.pk_status = ob.fk_status
									WHERE ob.pk_object = ?', [$id]);
						
		
		return $app->json(array('object'=>$object_1), 200);
});

//Утилизация объекта
$app->put('/new_delete/{id}', function (Request $req, $id) use ($app) {
		$conn = $app['db'];
		$state = $req->get('state');
		$date = $req->get('date');
		$act = $req->get('act');
		
		$conn->insert('status_history', ['date_status_history' => $date, 'act_status_history'=>$act, 'fk_status'=>$state, 'fk_object'=>$id]);		
		
		$conn->executeUpdate('UPDATE object SET fk_status=? WHERE pk_object = ?',[$state, $id]);	
	
	
		$object_1 = $conn->fetchAssoc('SELECT * 
									FROM object ob 
									LEFT JOIN type t ON t.pk_type = ob.fk_type 
									LEFT JOIN place pl ON pl.pk_place = ob.fk_place 
									LEFT JOIN fio f ON f.pk_fio = pl.fk_fio 
									LEFT JOIN vsp v ON v.pk_vsp = pl.fk_vsp 
									LEFT JOIN status st ON st.pk_status = ob.fk_status
									WHERE ob.pk_object = ?', [$id]);
						
		
		return $app->json(array('object'=>$object_1), 200);
});

//Изменение паспорта объекта
$app->post('/new_obj_pas/{id}', function (Request $req, $id) use ($app) {
		$conn = $app['db'];
		$date = $req->get('date');
		$now = date('Y-m-d H-i-s');
		
		foreach ($_FILES as $file) 
		{			
			$path = "images/pasport/".$now." ".$file['name'];
			move_uploaded_file($file['tmp_name'], $path);
		}
		$id_obj = $conn->fetchAssoc('SELECT * FROM  object WHERE pk_object = ?', [$id]);
		
		$conn->insert('pasport_history', ['photo_passport' => $id_obj['pasport'], 'date_pasport'=>$date, 'fk_object'=>$id]);		

		$conn->executeUpdate('UPDATE object SET pasport=? WHERE pk_object = ?',[$path, $id]);

		$object_1 = $conn->fetchAssoc('SELECT * 
									FROM object ob 
									LEFT JOIN type t ON t.pk_type = ob.fk_type 
									LEFT JOIN place pl ON pl.pk_place = ob.fk_place 
									LEFT JOIN fio f ON f.pk_fio = pl.fk_fio 
									LEFT JOIN vsp v ON v.pk_vsp = pl.fk_vsp 
									LEFT JOIN status st ON st.pk_status = ob.fk_status
									WHERE ob.pk_object = ?', [$id]);
						
		
		return $app->json(array('object'=>$object_1), 200);
});

//Редактирование объекта
$app->put('/edit_obj/{id}', function (Request $req, $id) use ($app) {
		$conn = $app['db'];
		$name = $req->get('name');
		$type = $req->get('type');
		$date = $req->get('date');
		$factory_number = $req->get('factory_number');
		$inventory_number = $req->get('inventory_number');
		
		$object = $conn->fetchAssoc('SELECT * FROM object WHERE pk_object = ?', [$id] );
		$place = $object['fk_place'];							
		$state = $object['fk_status'];	
		
		$conn->executeUpdate('UPDATE object SET name=?, fk_type=?, date_buy=?, factory_number=?, inventory_number=?, fk_place=?, fk_status=?  WHERE pk_object = ?',[$name,$type,$date,$factory_number,$inventory_number, $place, $state, $id]);
		
		$object_1 = $conn->fetchAssoc('SELECT * 
									FROM object ob 
									LEFT JOIN type t ON t.pk_type = ob.fk_type 
									LEFT JOIN place pl ON pl.pk_place = ob.fk_place 
									LEFT JOIN fio f ON f.pk_fio = pl.fk_fio 
									LEFT JOIN vsp v ON v.pk_vsp = pl.fk_vsp 
									LEFT JOIN status st ON st.pk_status = ob.fk_status
									WHERE ob.pk_object = ?', [$id] );		
		
		return $app->json(array('object'=>$object_1), 200);
});

//Для вывода текущего всп и фио и объекта
$app->get('/get_obj_info_modal/{id}', function ($id) use ($app) {
    $conn = $app['db'];
    $object = $conn->fetchAssoc('SELECT * 
									FROM object ob 
									LEFT JOIN type t ON t.pk_type = ob.fk_type 
									LEFT JOIN place pl ON pl.pk_place = ob.fk_place 
									LEFT JOIN fio f ON f.pk_fio = pl.fk_fio 
									LEFT JOIN vsp v ON v.pk_vsp = pl.fk_vsp 
									LEFT JOIN status st ON st.pk_status = ob.fk_status
									WHERE ob.pk_object = ?', [$id]);
	
	$vsps = $conn->fetchAll('SELECT * FROM  vsp');
	$fios = $conn->fetchAll('SELECT * FROM  fio');
	$types = $conn->fetchAll('SELECT * FROM  type');
								
	return $app->json(array('object'=>$object, 'vsps'=>$vsps, 'fios'=>$fios, 'type'=>$types), 200);
});

//Для проверки на возможность удаления объекта
$app->get('/get_del_info/{id}', function ($id) use ($app) {
   
	$conn = $app['db'];
	$repair = $conn->fetchAll('SELECT * 
								FROM repair 
								WHERE fk_object = ?', [$id]);	
								
	$place = $conn->fetchAll('SELECT *, 
									pl1.room AS room_from, 
									pl2.room AS room_to, 
									v1.name_vsp AS name_vsp_from, 
									v2.name_vsp AS name_vsp_to, 
									f1.surname AS surname_from, 
									f2.surname AS surname_to, 
									f1.fio_name AS fio_name_from, 
									f2.fio_name AS fio_name_to, 
									f1.patronymic AS patronymic_from, 
									f2.patronymic AS patronymic_to 
									FROM place_history ph 
									LEFT JOIN place pl1 ON pl1.pk_place = ph.fk_place_from 
									LEFT JOIN place pl2 ON pl2.pk_place = ph.fk_place_to 
									LEFT JOIN vsp v1 ON v1.pk_vsp = pl1.fk_vsp 
									LEFT JOIN vsp v2 ON v2.pk_vsp = pl2.fk_vsp 
									LEFT JOIN fio f1 ON f1.pk_fio = pl1.fk_fio 
									LEFT JOIN fio f2 ON f2.pk_fio = pl2.fk_fio 
									WHERE ph.fk_object = ?', [$id]);
	$pasport = $conn->fetchAll('SELECT *
								FROM pasport_history
								WHERE fk_object = ?', [$id]);	
	$state = $conn->fetchAll('SELECT * 
								FROM status_history sh
								LEFT JOIN status st ON st.pk_status = sh.fk_status
								WHERE sh.fk_object = ?', [$id]);	
								
								
	return $app->json(array('repair'=>$repair, 'place'=>$place, 'pasport'=>$pasport, 'state'=>$state), 200);
});

//Проверка на существование объекта
$app->get('/get_obj_exist/{id}', function ($id) use ($app) {
   
	$conn = $app['db'];
	$object = $conn->fetchAssoc('SELECT * FROM object WHERE pk_object = ?', [$id]);									
	return $app->json(array('object'=>$object), 200);
});

//Удаление объекта
$app->delete('/del_obj/{id}', function ($id) use ($app) {
    $conn = $app['db'];
    $conn->delete('object', ['pk_object' => $id]);
	
    return $app->json('', 200);
});

//Откат ремонта
$app->delete('/rol_obj_rep/{id}', function ($id) use ($app) {
    $conn = $app['db'];
	
	$id_rep = $conn->fetchAssoc('SELECT pk_repair FROM repair WHERE fk_object = ? ORDER BY pk_repair DESC LIMIT 1', [$id]);	
	$conn->delete('repair', ['pk_repair' => $id_rep['pk_repair']]);
	$object = $conn->fetchAll('SELECT * 
								FROM repair 
								WHERE fk_object = ?', [$id]);

    return $app->json(array('object'=>$object), 200);
});

//Откат перемещения
$app->delete('/rol_obj_plc/{id}', function ($id) use ($app) {
    $conn = $app['db'];
	
	$id_plc_his = $conn->fetchAssoc('SELECT * FROM place_history WHERE fk_object = ? ORDER BY pk_place_history DESC LIMIT 1', [$id]);		
	$conn->executeUpdate('UPDATE object SET fk_place=? WHERE pk_object = ?',[$id_plc_his['fk_place_from'], $id]);
	
	if ($id_plc_his['fk_place_from'] == 1)
	{
		$conn->executeUpdate('UPDATE object SET fk_status=1 WHERE pk_object = ?',[$id]);
	}
	$conn->delete('place', ['pk_place' => $id_plc_his['fk_place_to']]);	
	$conn->delete('place_history', ['pk_place_history' => $id_plc_his['pk_place_history']]);
	
	
	$object = $conn->fetchAll('SELECT *, 
									pl1.room AS room_from, 
									pl2.room AS room_to, 
									v1.name_vsp AS name_vsp_from, 
									v2.name_vsp AS name_vsp_to, 
									f1.surname AS surname_from, 
									f2.surname AS surname_to, 
									f1.fio_name AS fio_name_from, 
									f2.fio_name AS fio_name_to, 
									f1.patronymic AS patronymic_from, 
									f2.patronymic AS patronymic_to 
									FROM place_history ph 
									LEFT JOIN place pl1 ON pl1.pk_place = ph.fk_place_from 
									LEFT JOIN place pl2 ON pl2.pk_place = ph.fk_place_to 
									LEFT JOIN vsp v1 ON v1.pk_vsp = pl1.fk_vsp 
									LEFT JOIN vsp v2 ON v2.pk_vsp = pl2.fk_vsp 
									LEFT JOIN fio f1 ON f1.pk_fio = pl1.fk_fio 
									LEFT JOIN fio f2 ON f2.pk_fio = pl2.fk_fio 
									WHERE ph.fk_object = ?', [$id]);
								 
    return $app->json(array('object'=>$object), 200);
});

//Откат паспортов
$app->delete('/rol_obj_pas/{id}', function ($id) use ($app) {
    $conn = $app['db'];
	
	$id_pas = $conn->fetchAssoc('SELECT * FROM pasport_history WHERE fk_object = ? ORDER BY pk_pasport_history DESC LIMIT 1', [$id]);	
	
	$conn->executeUpdate('UPDATE object SET pasport=? WHERE pk_object = ?',[$id_pas['photo_passport'], $id]);
	
	$conn->delete('pasport_history', ['pk_pasport_history' => $id_pas['pk_pasport_history']]);
	
	$object = $conn->fetchAll('SELECT * 
								FROM pasport_history 
								WHERE fk_object = ?', [$id]);

    return $app->json(array('object'=>$object), 200);
});

//Откат статусов
$app->delete('/rol_obj_sta/{id}', function ($id) use ($app) {
    $conn = $app['db'];
	
	$id_pas = $conn->fetchAssoc('SELECT * FROM status_history WHERE fk_object = ? ORDER BY pk_status_history DESC LIMIT 1', [$id]);	
	if($id_pas['fk_status'] == 4)
	{
		$conn->executeUpdate('UPDATE object SET fk_status=3 WHERE pk_object = ?',[$id]);
	}
	else 
	{
		if($id_pas['fk_status'] == 3)
		{
			$conn->executeUpdate('UPDATE object SET fk_status=2 WHERE pk_object = ?',[$id]);
		}
	}
	
	$conn->delete('status_history', ['pk_status_history' => $id_pas['pk_status_history']]);
	
	$object = $conn->fetchAll('SELECT * 
								FROM status_history sh
								LEFT JOIN status st ON st.pk_status = sh.fk_status
								WHERE sh.fk_object = ?', [$id]);
    return $app->json(array('object'=>$object), 200);

});

//Получение списка объектов по статусам
$app->get('/get_obj_with_stat/{id}', function ($id) use ($app) {
	$conn = $app['db'];
	if($id == 1 || $id == 2)
		$object = $conn->fetchAll('SELECT * 
									FROM object ob 
									LEFT JOIN type t ON t.pk_type = ob.fk_type 
									LEFT JOIN place pl ON pl.pk_place = ob.fk_place 
									LEFT JOIN vsp v ON v.pk_vsp = pl.fk_vsp
									WHERE fk_status = 1 OR fk_status = 2');			
	else if($id == 3 )
		$object = $conn->fetchAll('SELECT * 
									FROM object ob 
									LEFT JOIN type t ON t.pk_type = ob.fk_type 
									LEFT JOIN place pl ON pl.pk_place = ob.fk_place 
									LEFT JOIN vsp v ON v.pk_vsp = pl.fk_vsp
									WHERE fk_status = 2');	
	else if($id == 4)
		$object = $conn->fetchAll('SELECT * 
									FROM object ob 
									LEFT JOIN type t ON t.pk_type = ob.fk_type 
									LEFT JOIN place pl ON pl.pk_place = ob.fk_place 
									LEFT JOIN vsp v ON v.pk_vsp = pl.fk_vsp
									WHERE fk_status = 3');				
	else if($id == 0)
		$object = $conn->fetchAll('SELECT * 
									FROM object ob 
									LEFT JOIN type t ON t.pk_type = ob.fk_type 
									LEFT JOIN place pl ON pl.pk_place = ob.fk_place 
									LEFT JOIN vsp v ON v.pk_vsp = pl.fk_vsp');				
	return $app->json(array('object'=>$object), 200);
});

//Информация о всем)
$app->get('/all_inf/', function () use ($app) {
		$conn = $app['db'];
		$objects = $conn->fetchAll('SELECT * 
									FROM object ob 
									LEFT JOIN type t ON t.pk_type = ob.fk_type 
									LEFT JOIN place pl ON pl.pk_place = ob.fk_place 
									LEFT JOIN vsp v ON v.pk_vsp = pl.fk_vsp');
									
		$types = $conn->fetchAll('SELECT * FROM  type');
		$vsps = $conn->fetchAll('SELECT * FROM  vsp');
		$fios = $conn->fetchAll('SELECT * FROM  fio');
		$status = $conn->fetchAll('SELECT * FROM  status');
		$places = $conn->fetchAll('SELECT * FROM  place');
		
		return $app->json(array('objects'=>$objects, 'types'=>$types, 'vsps'=>$vsps, 'fios'=>$fios,'places'=>$places,'status'=>$status), 200);
});

//Изменение местоположения объекта
$app->put('/new_place_all/', function (Request $req) use ($app) {
		$conn = $app['db'];
		$vsp = $req->get('vsp');
		$room = $req->get('room');
		$state = $req->get('state');
		$date = $req->get('date');
		$fio = $req->get('fio');
		$ids = $req->get('id');
		
		
		
		foreach($ids as $id)
		{
			$conn->insert('place', ['fk_vsp' => $vsp, 'room'=>$room, 'fk_fio'=>$fio]);	
				
			$place = $conn->fetchAssoc('SELECT pk_place FROM place ORDER BY pk_place DESC LIMIT 1');
			
			$object = $conn->fetchAssoc('SELECT * FROM  object WHERE pk_object = ?', [$id]);
			
			$object_place = $object['fk_place'];
			
			$conn->insert('place_history', ['fk_object' => $id, 'fk_place_to'=>$place['pk_place'], 'date_move'=> $date, 'fk_place_from'=> $object_place]);			

			$conn->executeUpdate('UPDATE object SET fk_place=?, fk_status=? WHERE pk_object = ?',[$place['pk_place'], $state, $id]);
		}
		
		$object = $conn->fetchAll('SELECT * 
									FROM object ob 
									LEFT JOIN type t ON t.pk_type = ob.fk_type 
									LEFT JOIN place pl ON pl.pk_place = ob.fk_place 
									LEFT JOIN vsp v ON v.pk_vsp = pl.fk_vsp
									WHERE fk_status = 1 OR fk_status = 2');	
						
		
		return $app->json(array('object'=>$object), 200);
});

//Ремонт объектов
$app->put('/new_repair_all/', function (Request $req) use ($app) {
		$conn = $app['db'];
		$date = $req->get('date');
		$cause = $req->get('cause');
		$cost = $req->get('cost');
		$ids = $req->get('id');
		
		foreach($ids as $id)
		{		
			$conn->insert('repair', ['fk_object' => $id, 'date_repair'=>$date, 'cost'=>$cost, 'text_cause'=>$cause]);
		}
		$object = $conn->fetchAll('SELECT * 
									FROM object ob 
									LEFT JOIN type t ON t.pk_type = ob.fk_type 
									LEFT JOIN place pl ON pl.pk_place = ob.fk_place 
									LEFT JOIN vsp v ON v.pk_vsp = pl.fk_vsp
									WHERE fk_status = 1 OR fk_status = 2');									
		return $app->json(array('object'=>$object), 200);
});

//Списание объектов
$app->put('/new_write_off_all/', function (Request $req) use ($app) {
		$conn = $app['db'];
		$state = $req->get('state');
		$date = $req->get('date');
		$act = $req->get('act');
		$ids = $req->get('id');
		
		foreach($ids as $id)
		{		
			$conn->insert('status_history', ['date_status_history' => $date, 'act_status_history'=>$act, 'fk_status'=>$state, 'fk_object'=>$id]);				
			$conn->executeUpdate('UPDATE object SET fk_status=? WHERE pk_object = ?',[$state, $id]);		
		}
		

		$object = $conn->fetchAll('SELECT * 
									FROM object ob 
									LEFT JOIN type t ON t.pk_type = ob.fk_type 
									LEFT JOIN place pl ON pl.pk_place = ob.fk_place 
									LEFT JOIN vsp v ON v.pk_vsp = pl.fk_vsp
									WHERE fk_status = 2');
						
		
		return $app->json(array('object'=>$object), 200);
});

//Утилизация объектов
$app->put('/new_delete_all/', function (Request $req) use ($app) {
		$conn = $app['db'];
		$state = $req->get('state');
		$date = $req->get('date');
		$act = $req->get('act');
		$ids = $req->get('id');
		
		foreach($ids as $id)
		{
		$conn->insert('status_history', ['date_status_history' => $date, 'act_status_history'=>$act, 'fk_status'=>$state, 'fk_object'=>$id]);		
		
		$conn->executeUpdate('UPDATE object SET fk_status=? WHERE pk_object = ?',[$state, $id]);	
		}

		$object = $conn->fetchAll('SELECT * 
									FROM object ob 
									LEFT JOIN type t ON t.pk_type = ob.fk_type 
									LEFT JOIN place pl ON pl.pk_place = ob.fk_place 
									LEFT JOIN vsp v ON v.pk_vsp = pl.fk_vsp
									WHERE fk_status = 3');	
						
		
		return $app->json(array('object'=>$object), 200);
});

//Справочники
$app->get('/directory/', function () use ($app) {
		return $app['twig']->render('Directory.twig');
});

//Загрузка справочников
$app->get('/dir_fio/', function () use ($app) {
    $conn = $app['db'];
    $fio = $conn->fetchAll('select * from fio WHERE pk_fio != 1');

    return $app->json(array('fio'=>$fio), 200);
});

$app->get('/dir_vsp/', function () use ($app) {
    $conn = $app['db'];
    $vsp = $conn->fetchAll('select * from vsp WHERE pk_vsp != 1');

    return $app->json(array('vsp'=>$vsp), 200);
});

$app->get('/dir_type/', function () use ($app) {
    $conn = $app['db'];
    $type = $conn->fetchAll('select * from type');

    return $app->json(array('type'=>$type), 200);
});

//Удаление из справочников
$app->delete('/dir_fio/', function (Request $request) use ($app) {
    $conn = $app['db'];
	$id = $request->request->get('id');
    $conn->delete('fio', ['pk_fio' => $id]);
    return $app->json("ФИО удалено", 200);
});

$app->delete('/dir_vsp/', function (Request $request) use ($app) {
    $conn = $app['db'];
	$id = $request->request->get('id');
    $conn->delete('vsp', ['pk_vsp' => $id]);
    return $app->json("ВСП удалено", 200);
});

$app->delete('/dir_type/', function (Request $request) use ($app) {
    $conn = $app['db'];
	$id = $request->request->get('id');
    $conn->delete('type', ['pk_type' => $id]);
    return $app->json("Тип оборудования удален", 200);
});

//Добавление в справочники
$app->post('/dir_fio/', function (Request $request) use ($app) {
    $conn = $app['db'];
	$name = $request->request->get('name');
	$surname = $request->request->get('surname');
	$patronymic = $request->request->get('patronymic');
    $conn->insert('fio', ['patronymic' => $patronymic, 'fio_name' => $name, 'surname' => $surname]);
	
    $fio = $conn->fetchAssoc('SELECT * FROM fio ORDER BY pk_fio DESC LIMIT 1');

    return $app->json(array('fio'=>$fio), 200);
});

$app->post('/dir_vsp/', function (Request $request) use ($app) {
    $conn = $app['db'];
	$name = $request->request->get('name');
    $conn->insert('vsp', ['name_vsp' => $name]);
	
    $vsp = $conn->fetchAssoc('SELECT * FROM vsp ORDER BY pk_vsp DESC LIMIT 1');

    return $app->json(array('vsp'=>$vsp), 200);
});

$app->post('/dir_type/', function (Request $request) use ($app) {
    $conn = $app['db'];
	$name = $request->request->get('name');
    $conn->insert('type', ['type_name' => $name]);
	
    $type = $conn->fetchAssoc('SELECT * FROM type ORDER BY pk_type DESC LIMIT 1');

    return $app->json(array('type'=>$type), 200);
});

//Получение инфы по одной строке справочника
$app->get('/dir_fio_one/', function (Request $request) use ($app) {
    $conn = $app['db'];
	$id = $request->get('id');
    $fio = $conn->fetchAssoc('select * from fio where pk_fio = ?', [$id]);

    return $app->json(array('fio'=>$fio), 200);
});

$app->get('/dir_vsp_one/', function (Request $request) use ($app) {
    $conn = $app['db'];
	$id = $request->get('id');
    $vsp = $conn->fetchAssoc('select * from vsp WHERE pk_vsp = ?',[$id]);

    return $app->json(array('vsp'=>$vsp), 200);
});

$app->get('/dir_type_one/', function (Request $request) use ($app) {
    $conn = $app['db'];
	$id = $request->get('id');
    $type = $conn->fetchAssoc('select * from type WHERE pk_type = ?',[$id]);

    return $app->json(array('type'=>$type), 200);
});

//Редактирование справочников
$app->put('/dir_fio/', function (Request $request) use ($app) {
    $conn = $app['db'];
	$name = $request->request->get('name');
	$surname = $request->request->get('surname');
	$patronymic = $request->request->get('patronymic');
	$id = $request->request->get('id');
    $conn->executeUpdate('UPDATE fio SET patronymic=?, fio_name=?,surname=? WHERE pk_fio = ?',[$patronymic, $name, $surname, $id]);
	
    return $app->json(array(), 200);
});

$app->put('/dir_vsp/', function (Request $request) use ($app) {
    $conn = $app['db'];
	$name = $request->request->get('name');
    $id = $request->request->get('id');
    $conn->executeUpdate('UPDATE vsp SET name_vsp=? WHERE pk_vsp = ?',[$name, $id]);

    return $app->json(array(), 200);
});

$app->put('/dir_type/', function (Request $request) use ($app) {
    $conn = $app['db'];
	$name = $request->request->get('name');
    $id = $request->request->get('id');
    $conn->executeUpdate('UPDATE type SET type_name=? WHERE pk_type = ?',[$name, $id]);

    return $app->json(array(), 200);
});

//Отчеты
$app->get('/report/', function () use ($app) { 
		$conn = $app['db'];
		$vsp = $conn->fetchAll('select * from vsp');
		$fio = $conn->fetchAll('select * from fio WHERE pk_fio != 1');
		return $app['twig']->render('Report.twig',['vsp'=>$vsp, 'fio'=>$fio]);
});

//Поиск
$app->get('/get_report/', function (Request $request) use ($app) {
    $conn = $app['db'];
	$name = $request->get('name');
	$date_begin = $request->get('date_begin');
	$date_end = $request->get('date_end');
	$fio = $request->get('fio');
	$vsp = $request->get('vsp');
	$act = $request->get('act');
	$req = '';
	$where = '';
	$variables = array();
	if($name!= '')
	{
		$where = ' WHERE ob.name RLIKE ?';
		$variables[] = $name;
	}
	if($act == 1)
	{
		$req = ' 	SELECT * ,
						pl1.room AS room_from, 
						pl2.room AS room_to, 
						v1.name_vsp AS name_vsp_from, 
						v2.name_vsp AS name_vsp_to, 
						f1.surname AS surname_from, 
						f2.surname AS surname_to, 
						f1.fio_name AS fio_name_from, 
						f2.fio_name AS fio_name_to, 
						f1.patronymic AS patronymic_from, 
						f2.patronymic AS patronymic_to
					FROM place_history ph 
					LEFT JOIN object ob ON ob.pk_object = ph.fk_object 
					LEFT JOIN place pl1 ON pl1.pk_place = ph.fk_place_from 
					LEFT JOIN place pl2 ON pl2.pk_place = ph.fk_place_to 
					LEFT JOIN vsp v1 ON v1.pk_vsp = pl1.fk_vsp 
					LEFT JOIN vsp v2 ON v2.pk_vsp = pl2.fk_vsp 
					LEFT JOIN fio f1 ON f1.pk_fio = pl1.fk_fio 
					LEFT JOIN fio f2 ON f2.pk_fio = pl2.fk_fio 
					';
		
		if($date_begin)
		{
			if($where) $where .= ' AND ';
			else $where .= ' WHERE ';
			$where .= ' ph.date_move >= ? ';
			$variables[] = $date_begin;
		}
		if($date_end)
		{
			if($where) $where .= ' AND ';
			else $where .= ' WHERE ';
			$where .= ' ph.date_move <= ? ';
			$variables[] = $date_end;
		}
	}
	else if($act == 2)
	{
		$req = '	SELECT *
					FROM repair r 
					LEFT JOIN object ob ON ob.pk_object = r.fk_object 
					LEFT JOIN place pl ON pl.pk_place = ob.fk_place
					LEFT JOIN fio f ON f.pk_fio = pl.fk_fio
					LEFT JOIN vsp v ON v.pk_vsp = pl.fk_vsp
					';
		if($date_begin)
		{
			if($where) $where .= ' AND ';
			else $where .= ' WHERE ';
			$where .= ' r.date_repair >= ? ';
			$variables[] = $date_begin;
		}
		if($date_end)
		{
			if($where) $where .= ' AND ';
			else $where .= ' WHERE ';
			$where .= ' r.date_repair <= ? ';
			$variables[] = $date_end;
		}	
		if($fio && $fio != "0")
		{
			if($where) $where .= ' AND ';
			else $where .= ' WHERE ';
			$where .= ' f.pk_fio = ? ';
			$variables[] = $fio;
		}
		if($vsp && $vsp != "0")
		{
			if($where) $where .= ' AND ';
			else $where .= ' WHERE ';
			$where .= ' v.pk_vsp = ? ';
			$variables[] = $vsp;
		}
	}
	$req .= $where;
	
    $object = $conn->fetchAll($req, $variables);
    return $app->json(array('object'=>$object), 200);
});

//Вывод в Excel
$app->get('/excel_report/', function (Request $request) use ($app) {
	$conn = $app['db'];
	$name = $request->get('name');
	$date_begin = $request->get('date_begin');
	$date_end = $request->get('date_end');
	$fio = $request->get('fio');
	$vsp = $request->get('vsp');
	$act = $request->get('act');
	$report_data = $request->get('report_data');

	$xls = new \PHPExcel();
	$xls->setActiveSheetIndex(0);
	$sheet = $xls->getActiveSheet();

	$title = '';
	if($act == 1) $title = "Отчет по перемещениям";
	else if($act == 2) $title = "Отчет по ремонту";
	$sheet->setTitle($title);
	 
	$title .= ' от '.date('d-m-Y');
	// Вставляем текст в ячейку A1
	$sheet->setCellValue("A1", $title);
	 
	// Объединяем ячейки
	$sheet->mergeCells('A1:E1');
	 
	// Выравнивание текста
	$sheet->getStyle('A1')->getAlignment()->setHorizontal(
		PHPExcel_Style_Alignment::HORIZONTAL_CENTER);

	$sheet->getColumnDimension('A')->setWidth(5);
	$sheet->getColumnDimension('B')->setAutoSize(true);
	$sheet->getColumnDimension('C')->setAutoSize(true);
	$sheet->getColumnDimension('D')->setAutoSize(true);
	$sheet->getColumnDimension('E')->setAutoSize(true);
	
	$count = 3;
	if($date_begin)
	{
		$sheet->setCellValue("B".$count, 'Начало периода:');
		$sheet->setCellValue("C".$count, $date_begin);
		$count += 1;
	}
	if($date_end)
	{
		$sheet->setCellValue("B".$count, 'Конец периода:');
		$sheet->setCellValue("C".$count, $date_end);
		$count += 1;
	}
	if($act == 2 && $vsp > 0)
	{
		$sheet->setCellValue("B".$count, 'ВСП:');
		$sheet->setCellValue("C".$count, $request->get('vsp_text'));
		$count += 1;
	}
	if($act == 2 && $fio > 0)
	{
		$sheet->setCellValue("B".$count, 'Эксп. лицо:');
		$sheet->setCellValue("C".$count, $request->get('fio_text'));
		$count += 1;
	}
	$count += 1;

	for ($i = 0; $i < count($report_data); $i++) {
		for ($j = 0; $j < count($report_data[$i]); $j++) {
			$sheet->setCellValueByColumnAndRow(
											  $j,
											  $i+$count,
											  $report_data[$i][$j]);
			$sheet->getStyleByColumnAndRow($j, $i+$count)->getAlignment()->
					setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_LEFT);
		}
	}
	 
	// Выводим содержимое файла
	$objWriter = new PHPExcel_Writer_Excel5($xls);
	$path = '\images\reports\report '.date('d-m-Y H-i-s').'.xls';
	$objWriter->save(__DIR__.$path);
    return $app->json(array('path'=>$path), 200);
});

//Удаление файла с сервера
$app->delete('/file_from_server/', function (Request $request) use ($app) {
	$conn = $app['db'];
    return $app->json(200);
});


//Импорт
$app->get('/import/', function () use ($app) {
		return $app['twig']->render('Import.twig');
});

//Поиск
$app->get('/search/', function (Request $request) use ($app) {
    $conn = $app['db'];
	$name = $request->get('name');
	$type = $request->get('type');
	$date_begin = $request->get('date_begin');
	$date_end = $request->get('date_end');
	$factory_number = $request->get('factory_number');
	$inventory_number = $request->get('inventory_number');
	$fio = $request->get('fio');
	$vsp = $request->get('vsp');
	$room = $request->get('room');
	$status = $request->get('status');
	$req = 'SELECT *
			FROM object ob
			LEFT JOIN type t ON t.pk_type = ob.fk_type
			LEFT JOIN status st ON st.pk_status = ob.fk_status
			LEFT JOIN place pl ON pl.pk_place = ob.fk_place
			LEFT JOIN fio f ON f.pk_fio = pl.fk_fio
			LEFT JOIN vsp v ON v.pk_vsp = pl.fk_vsp ';
	$where = '';
	$variables = array();
	if($name!= '')
	{
		$where = " WHERE ";
		$names = explode(",", $name);
		$array_in = '';
		foreach($names as $n) 
		{
			if($array_in != '') $array_in .= ' OR ';
			$array_in .= ' ob.name RLIKE ?';
			$variables[] = $n;
		}
		$where .= $array_in;
		$where .= " ";
	}
	if($type)
	{
		if($where) $where .= ' AND t.pk_type IN (';
		else $where .= ' WHERE t.pk_type IN (';
		$array_in = '';
		foreach($type as $t) 
		{
			if($array_in != '') $array_in .= ', ';
			$array_in .= '?';
			$variables[] = $t;
		}
		$where .= $array_in;
		$where .= ') ';
	}
	if($date_begin)
	{
		if($where) $where .= ' AND ';
		else $where .= ' WHERE ';
		$where .= ' ob.date_buy >= ? ';
		$variables[] = $date_begin;
	}
	if($date_end)
	{
		if($where) $where .= ' AND ';
		else $where .= ' WHERE ';
		$where .= ' ob.date_buy <= ? ';
		$variables[] = $date_end;
	}
	if($factory_number)
	{
		if($where) $where .= ' AND ';
		else $where .= ' WHERE ';
		$factory_numbers = explode(",", $factory_number);
		$array_in = '';
		foreach($factory_numbers as $f) 
		{
			if($array_in != '') $array_in .= ' OR ';
			$array_in .= ' ob.factory_number RLIKE ?';
			$variables[] = $f;
		}
		$where .= $array_in;
	}
	if($inventory_number)
	{
		if($where) $where .= ' AND ';
		else $where .= ' WHERE ';
		$inventory_numbers = explode(",", $inventory_number);
		$array_in = '';
		foreach($inventory_numbers as $i) 
		{
			if($array_in != '') $array_in .= ' OR ';
			$array_in .= ' ob.inventory_number RLIKE ?';
			$variables[] = $i;
		}
		$where .= $array_in;
	}
	if($fio)
	{
		if($where) $where .= ' AND f.pk_fio IN \'(';
		else $where .= ' WHERE f.pk_fio IN (';
		$array_in = '';
		foreach($fio as $f) 
		{
			if($array_in != '') $array_in .= ', ';
			$array_in .= '?';
			$variables[] = $f;
		}
		$where .= $array_in;
		$where .= ') \' ';
	}
	if($vsp)
	{
		if($where) $where .= ' AND v.pk_vsp IN (';
		else $where .= ' WHERE v.pk_vsp IN (';
		$array_in = '';
		foreach($vsp as $v) 
		{
			if($array_in != '') $array_in .= ', ';
			$array_in .= '?';
			$variables[] = $v;
		}
		$where .= $array_in;
		$where .= ') ';
	}
	if($room)
	{
		if($where) $where .= ' AND ';
		else $where .= ' WHERE ';
		$room = explode(",", $room);
		$array_in = '';
		foreach($room as $r) 
		{
			if($array_in != '') $array_in .= ' OR ';
			$array_in .= ' pl.room RLIKE ?';
			$variables[] = $r;
		}
		$where .= $array_in;
	}
	if($status)
	{
		if($where) $where .= ' AND st.pk_status IN (';
		else $where .= ' WHERE st.pk_status IN (';
		$array_in = '';
		foreach($status as $st) 
		{
			if($array_in != '') $array_in .= ', ';
			$array_in .= '?';
			$variables[] = $st;
		}
		$where .= $array_in;
		$where .= ') ';
	}
	$req .= $where;
	
    $object = $conn->fetchAll($req, $variables);
								
    return $app->json(array('object'=>$object), 200);
});

$app->run();