# netbill
Proyecto Net-Bill

## Instalación

+ Después de descargar el proyecto entramos a este.

        $ cd nombreRepositorio

+ Ejecutamos el siguiente comando.

        $ composer install
    
+ Modificamos el nombre del archivo __.env.example.__ por __.env__ y agregamos nuestras credenciales.

+ Ejecutamos las migraciones.

        $ php artisan migrate

+ Por ultimo solo debemos generar una key para nuestra app.

         $ php artisan key:generate

+ Listo ya podemos ejecutar el proyecto NetBill.

        $ php artisan serve
