<?php
/*
declare(strict_types=1);
class classA {
  function test(int $integer)
  {
      var_dump($integer);
  }
}


test('3 dogs');
echo "############################\n";
$a= new classA;
try{
  for ($i=0;$i<10;$i++)
  {
  $a->test(3);
  $a->test("aaa");
  //test(3);
  //test('3');
  //test('not an integer');
  }
}
catch(Throwable $ex) {
  echo "catched $ex\n";
}
echo "done.\n";*/


/**
 * Scalar type declarations
 */
//declare(strict_types=1);

/*function exception_handler(Throwable $exception) {
  echo "Exception non attrapée : " , $exception->getMessage(), "\n";
}

set_error_handler('exception_handler');
*/

declare(strict_types=1);
function addf(int $a) {
    return $a ;
}
var_dump(addf(1));
var_dump(addf(1.2));
var_dump(addf(1));
