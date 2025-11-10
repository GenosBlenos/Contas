import os
import re
import logging
import fitz  # PyMuPDF
import pdfplumber
import easyocr
import numpy as np
from PIL import Image
import cv2

# --- Configuração ---
logger = logging.getLogger(__name__)

# --- Inicialização de Leitores (Singleton) ---
EASYOCR_READER = None
EASYOCR_ERROR = None

def initialize_easyocr_reader():
    """Inicializa o leitor EasyOCR de forma segura, uma única vez."""
    global EASYOCR_READER, EASYOCR_ERROR
    if EASYOCR_READER is None and EASYOCR_ERROR is None:
        try:
            logger.info("Inicializando o leitor OCR (EasyOCR)... Pode levar um momento.")
            EASYOCR_READER = easyocr.Reader(['pt', 'en'], gpu=False)
            logger.info("EasyOCR carregado com sucesso.")
        except Exception as e:
            EASYOCR_ERROR = e
            logger.error(f"ERRO CRÍTICO AO INICIALIZAR EasyOCR: {e}")

def _preprocess_image_for_ocr(image: Image.Image) -> np.ndarray:
    """Aplica pré-processamento a uma imagem para melhorar a qualidade do OCR."""
    # Converte para formato OpenCV (numpy array) e escala de cinza
    img_cv = np.array(image.convert('L'))
    # Aplica um thresholding binário adaptativo para limpar a imagem
    # Isso ajuda a remover ruído de fundo e a destacar o texto
    processed_img = cv2.adaptiveThreshold(
        img_cv, 255, cv2.ADAPTIVE_THRESH_GAUSSIAN_C, cv2.THRESH_BINARY, 11, 2
    )
    return processed_img

def _run_easyocr(image: np.ndarray, filename: str) -> str:
    """Executa o EasyOCR em uma imagem pré-processada."""
    if not EASYOCR_READER:
        logger.warning(f"[{filename}] EasyOCR não está disponível, pulando a extração.")
        return ""
    try:
        result = EASYOCR_READER.readtext(image, detail=0, paragraph=True)
        return " ".join(result).strip()
    except Exception as e:
        logger.error(f"[{filename}] Falha ao executar EasyOCR: {e}")
        return ""

def extract_text_from_pdf(pdf_path: str, use_ocr=True) -> str:
    """
    Extrai texto de um PDF usando uma abordagem em camadas:
    1. Tenta extrair texto nativo com pdfplumber (ótimo para layouts complexos).
    2. Tenta extrair texto nativo com PyMuPDF (rápido e eficiente).
    3. Se nenhum texto nativo for encontrado, recorre ao OCR usando PyMuPDF e EasyOCR.
    """
    filename = os.path.basename(pdf_path)
    full_text = ''

    # --- Camada 1: Extração de texto nativo com pdfplumber ---
    try:
        logger.debug(f"[{filename}] Tentando extração de texto nativo com pdfplumber.")
        with pdfplumber.open(pdf_path, password='') as pdf:
            pages = [page.extract_text() for page in pdf.pages if page.extract_text()]
            full_text = " ".join(pages)
        full_text = re.sub(r'\s+', ' ', full_text).strip()
        if len(full_text) > 50:
            logger.info(f"[{filename}] Texto extraído com sucesso via pdfplumber.")
            return full_text
        logger.warning(f"[{filename}] pdfplumber extraiu texto mínimo.")
    except Exception as e: # pdfplumber pode falhar em PDFs criptografados ou malformados
        logger.warning(f"[{filename}] pdfplumber falhou: {e}. Tentando próximo método.")

    # --- Camada 2: Extração de texto nativo com PyMuPDF ---
    try:
        logger.debug(f"[{filename}] Tentando extração de texto nativo com PyMuPDF (fitz).")
        with fitz.open(pdf_path) as doc:
            full_text = " ".join(page.get_text("text", sort=True) for page in doc)
        full_text = re.sub(r'\s+', ' ', full_text).strip()
        if len(full_text) > 50:
            logger.info(f"[{filename}] Texto extraído com sucesso via PyMuPDF.")
            return full_text
        logger.warning(f"[{filename}] PyMuPDF também extraiu texto mínimo. Prosseguindo para OCR se ativado.")
    except Exception as e:
        logger.warning(f"[{filename}] PyMuPDF falhou: {e}. Prosseguindo para OCR se ativado.")

    # --- Camada 3: Extração via OCR (se habilitado e necessário) ---
    if not use_ocr:
        logger.info(f"[{filename}] Extração por OCR desabilitada. Nenhum texto extraído.")
        return ""
    
    logger.info(f"[{filename}] Nenhuma extração de texto nativo bem-sucedida. Iniciando pipeline de OCR com PyMuPDF e EasyOCR.")
    initialize_easyocr_reader()

    final_text_parts = []
    try:
        with fitz.open(pdf_path) as doc:
            for i, page in enumerate(doc):
                page_num = i + 1
                logger.debug(f"[{filename}] Processando OCR da página {page_num}/{len(doc)}.")
                
                # Renderiza a página como uma imagem usando PyMuPDF
                pix = page.get_pixmap(dpi=300)
                image = Image.frombytes("RGB", [pix.width, pix.height], pix.samples)
                
                processed_image = _preprocess_image_for_ocr(image)
                
                # Executa o EasyOCR
                ocr_text = _run_easyocr(processed_image, filename)
                if ocr_text:
                    logger.info(f"[{filename}] Página {page_num}: EasyOCR extraiu texto.")
                    final_text_parts.append(ocr_text)
                else:
                    logger.warning(f"[{filename}] Página {page_num}: EasyOCR não retornou texto.")

    except Exception as e:
        logger.error(f"[{filename}] Falha no pipeline de OCR com PyMuPDF/EasyOCR: {e}")
        return ""
            
    full_text = "\n".join(final_text_parts).strip()

    if not full_text:
        logger.error(f"[{filename}] Todas as tentativas de extração (incluindo OCR) falharam.")
    else:
        logger.info(f"[{filename}] Extração por OCR concluída com sucesso.")

    return full_text