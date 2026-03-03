# 🎬 LovingRoom - Cine Sincronizado

**LovingRoom** es una aplicación web ligera diseñada para crear una sala de cine virtual privada. Su objetivo principal es permitir a parejas o amigos ver películas locales o vídeos de YouTube en perfecta sincronía, sin importar la distancia.

Ideal para mantener la conexión a distancia, compartiendo el control de la reproducción en tiempo real.

## ✨ Características Principales

- **Sincronización en tiempo real:** Reproducción, pausa y tiempo de visualización sincronizados automáticamente entre el anfitrión y los espectadores.
- **Soporte Multi-formato:** Reproduce vídeos locales (MP4, WEBM, MKV) alojados en el servidor.
- **Integración con YouTube:** Permite cargar y sincronizar vídeos directamente desde enlaces de YouTube.
- **Gestión de Subtítulos:** Soporte para subtítulos `.vtt` desde el servidor o carga manual desde el dispositivo del espectador.
- **Descarga de películas:** Los usuarios pueden descargar los archivos de vídeo locales directamente desde la interfaz.
- **Sistema de Roles:** Un "Anfitrión" controla la película, mientras que los "Espectadores" se sincronizan automáticamente con él.
- **Panel de Espectadores:** Muestra quién está conectado a la sala en todo momento.

## 🛠️ Requisitos Previos

Para ejecutar este proyecto, necesitas un entorno de servidor web básico:

- Un servidor web (Apache, Nginx, etc.) o un hosting compartido.
- Soporte para **PHP 7.4 o superior**.
- Permisos de escritura en el directorio raíz del proyecto (para que el script pueda crear/modificar el archivo `sync_state.json` y la carpeta `peliculas/`).

## 🚀 Instalación y Configuración

1.  **Clona o descarga el repositorio:**
    Sube los archivos del proyecto al directorio público de tu servidor web (por ejemplo, `public_html` o `/var/www/html/`).

2.  **Permisos de carpetas:**
    Asegúrate de que el servidor web tiene permisos para escribir en la carpeta donde has subido el archivo. El script intentará crear la carpeta `/peliculas` y el archivo `sync_state.json` automáticamente.

3.  **Añade tus películas:**
    Sube tus archivos de vídeo (`.mp4`, `.mkv`, `.webm`) a la carpeta `peliculas/`.
    - _Opcional:_ Si tienes subtítulos, renómbralos exactamente igual que el vídeo pero con extensión `.vtt` (ej. `mipelicula.mp4` y `mipelicula.vtt`) y súbelos a la misma carpeta.

## 📖 Instrucciones de Uso

### Para el Anfitrión (Quien controla la sala)

1.  Abre la aplicación en tu navegador y escribe tu nombre o apodo.
2.  Haz clic en el botón naranja **"👑 Tomar el Control (Anfitrión)"**.
3.  **Para ver un archivo local:** Selecciona una película de la lista inferior.
4.  **Para ver YouTube:** Pega el enlace en la caja de texto "Cargar vídeo de YouTube" y haz clic en "Cargar YT".
5.  Reproduce, pausa o adelanta el vídeo; los demás se sincronizarán contigo automáticamente.

### Para el Espectador

1.  Abre la URL de la aplicación que te ha compartido el anfitrión.
2.  Escribe tu nombre para entrar a la sala.
3.  Si el anfitrión ya ha iniciado el vídeo, verás un cartel grande en el reproductor. Haz clic en **"▶️ Toca para Sincronizar"** (esto es necesario por las políticas de autoplay de los navegadores web).
4.  ¡Ponte cómodo y disfruta! No necesitas tocar nada más; tu reproductor seguirá las órdenes del anfitrión.
5.  _Nota:_ Puedes usar el botón **⬇️** junto al nombre de la película para descargarla y verla sin conexión más tarde.

## 📂 Estructura del Directorio

/
├── index.php # Archivo principal (Frontend y Backend integrados)
├── sync_state.json # Archivo autogenerado (guarda el estado de la sincronización)
└── peliculas/ # Carpeta autogenerada (AQUÍ VAN TUS VÍDEOS)
├── pelicula_1.mp4
└── pelicula_1.vtt # Subtítulo (opcional)
