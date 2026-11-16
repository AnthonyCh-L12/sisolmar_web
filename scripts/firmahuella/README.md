# Extractor de firma y huella

El script `extractor.py` se ejecuta desde Laravel para procesar temporalmente una DJ escaneada. Laravel guarda el PDF y los JPG en un directorio temporal, devuelve los recortes como datos para la vista previa y elimina ese directorio al finalizar.

## Entorno local en Windows

Desde la raíz del proyecto Laravel:

```powershell
py -3 -m venv .venv
.\.venv\Scripts\python.exe -m pip install -r scripts\firmahuella\requirements.txt
```

Laravel detecta automáticamente `.venv\Scripts\python.exe`. Si se usa otro intérprete, se puede indicar en `.env` con `FIRMAHUELLA_PYTHON`.

El endpoint autenticado `POST /dj/extraer-firma-huella` recibe el archivo en el campo `pdf`. El máximo permitido es aproximadamente 1,2 MiB y la extracción usa 300 DPI.

El recorte deja 2 mm de margen a los lados y arriba, y corta los 10 mm inferiores para excluir los rótulos. Para recuadros completos de aproximadamente 114 × 45 mm (firma) y 76 × 45 mm (huella), las áreas resultantes son aproximadamente 110 × 33 mm y 72 × 33 mm. Si la firma o la huella alcanza esa franja inferior, también se recortará.
