# Portadas

## Agregar una portada

1. Preparar la imagen en formato `jpg`, `png` o `webp`, vertical (por ejemplo 600 × 900 px).

2. Nombrarla con el código del INCAA de la película (columna `pelicula_cod` de `database/informe_semanal.csv`):

   ```
   64260249.jpg
   ```

   También sirve el título en minúsculas, sin tildes ni símbolos y con guiones: `el-gato-con-sombrero.jpg`.

3. Copiarla a la carpeta `assets/portadas/` y recargar la página.

Las películas sin portada muestran un degradado con su título.

## Subir al hosting

```
scp assets/portadas/* proyecto-cine@ssh-proyecto-cine.alwaysdata.net:www/assets/portadas/
```
