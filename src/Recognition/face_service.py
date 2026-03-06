import cv2
import face_recognition
import numpy as np
import os

class FaceRecognitionService:
    def __init__(self, known_faces_dir="storage/faces"):
        self.known_face_encodings = []
        self.known_face_names = []
        self.known_faces_dir = known_faces_dir
        
        if not os.path.exists(known_faces_dir):
            os.makedirs(known_faces_dir)
            
        self.load_known_faces()

    def load_known_faces(self):
        """Carrega imagens do diretório storage/faces e gera encodings."""
        print("Carregando faces conhecidas...")
        for filename in os.listdir(self.known_faces_dir):
            if filename.endswith((".jpg", ".png", ".jpeg")):
                path = os.path.join(self.known_faces_dir, filename)
                image = face_recognition.load_image_file(path)
                encodings = face_recognition.face_encodings(image)
                
                if encodings:
                    self.known_face_encodings.append(encodings[0])
                    # O nome do arquivo (sem extensão) é usado como ID/Nome
                    self.known_face_names.append(os.path.splitext(filename)[0])
        print(f"Total de {len(self.known_face_names)} faces carregadas.")

    def run_recognition(self):
        """Inicia a captura de vídeo e reconhecimento em tempo real."""
        video_capture = cv2.VideoCapture(0)

        print("Iniciando reconhecimento facial (Pressione 'q' para sair)...")
        
        while True:
            ret, frame = video_capture.read()
            if not ret:
                break

            # Redimensionar para acelerar o processamento
            small_frame = cv2.resize(frame, (0, 0), fx=0.25, fy=0.25)
            rgb_small_frame = cv2.cvtColor(small_frame, cv2.COLOR_BGR2RGB)

            # Encontrar todas as faces e encodings no frame atual
            face_locations = face_recognition.face_locations(rgb_small_frame)
            face_encodings = face_recognition.face_encodings(rgb_small_frame, face_locations)

            face_names = []
            for face_encoding in face_encodings:
                # Ver se a face coincide com as conhecidas
                matches = face_recognition.compare_faces(self.known_face_encodings, face_encoding)
                name = "Desconhecido"

                # Usar a face com a menor distância (melhor match)
                face_distances = face_recognition.face_distance(self.known_face_encodings, face_encoding)
                if len(face_distances) > 0:
                    best_match_index = np.argmin(face_distances)
                    if matches[best_match_index]:
                        name = self.known_face_names[best_match_index]

                face_names.append(name)

            # Exibir resultados
            for (top, right, bottom, left), name in zip(face_locations, face_names):
                # Escalar de volta (já que processamos em 1/4 do tamanho)
                top *= 4
                right *= 4
                bottom *= 4
                left *= 4

                # Desenhar caixa ao redor da face
                color = (0, 0, 188) if name == "Desconhecido" else (0, 188, 0) # Vermelho Carmim vs Verde
                cv2.rectangle(frame, (left, top), (right, bottom), color, 2)

                # Desenhar etiqueta com nome
                cv2.rectangle(frame, (left, bottom - 35), (right, bottom), color, cv2.FILLED)
                cv2.putText(frame, name, (left + 6, bottom - 6), cv2.FONT_HERSHEY_DUPLEX, 0.8, (255, 255, 255), 1)

            cv2.imshow('Library Vision - Python Engine', frame)

            if cv2.waitKey(1) & 0xFF == ord('q'):
                break

        video_capture.release()
        cv2.destroyAllWindows()

if __name__ == "__main__":
    service = FaceRecognitionService()
    service.run_recognition()
