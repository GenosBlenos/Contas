import os
import pandas as pd
import logging
from pdf_processor import extract_text_from_pdf
from preprocessing import unified_preprocess_text

# Configuração de logging
logging.basicConfig(level=logging.INFO, format='%(asctime)s - %(levelname)s - %(message)s')
logger = logging.getLogger(__name__)

def create_dataset(base_dir, output_csv):
    """Cria o dataset a partir dos PDFs na pasta training_data."""
    training_data_dir = os.path.join(base_dir, 'training_data')
    categories = [d for d in os.listdir(training_data_dir) if os.path.isdir(os.path.join(training_data_dir, d))]
    
    data = []
    
    for category in categories:
        category_dir = os.path.join(training_data_dir, category)
        logger.info(f"Processando a categoria: {category}")
        for filename in os.listdir(category_dir):
            if filename.lower().endswith('.pdf'):
                pdf_path = os.path.join(category_dir, filename)
                text = extract_text_from_pdf(pdf_path)
                if text:
                    data.append({'text': text, 'category': category})
    
    if not data:
        logger.warning("Nenhum dado foi extraído. O dataset não será criado.")
        return False
        
    df = pd.DataFrame(data)
    
    # Salva o dataset em um arquivo CSV
    df.to_csv(output_csv, index=False, encoding='utf-8')
    logger.info(f"Dataset criado com sucesso em: {output_csv}")
    logger.info(f"Total de amostras: {len(df)}")
    logger.info("Distribuição das classes no novo dataset:")
    logger.info(df['category'].value_counts())
    
    return True

if __name__ == '__main__':
    base_dir = os.path.dirname(__file__)
    output_csv = os.path.join(base_dir, 'faturas_dataset.csv')
    
    # Cria um backup do dataset antigo se ele existir
    if os.path.exists(output_csv):
        backup_path = output_csv + '.bak'
        os.rename(output_csv, backup_path)
        logger.info(f"Backup do dataset antigo criado em: {backup_path}")
        
    if not create_dataset(base_dir, output_csv):
        logger.error("Falha ao criar o novo dataset.")
        # Restaura o backup se a criação falhar
        if os.path.exists(backup_path):
            os.rename(backup_path, output_csv)
            logger.info("Dataset antigo restaurado.")