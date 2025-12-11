import os
os.environ['TF_CPP_MIN_LOG_LEVEL'] = '3'  # Suppress TensorFlow warnings

import cv2
import numpy as np
import json
import base64
from PIL import Image
import io
import hashlib
import mysql.connector
from datetime import datetime
import threading
import time
from flask import Flask, request, jsonify
from flask_cors import CORS
from deepface import DeepFace


class FaceRecognitionSystem:
    def __init__(self, data_file="face_data.json", uploads_dir="uploads"):
        self.data_file = data_file
        self.uploads_dir = uploads_dir
        self.known_faces = {}

        # Haar Cascade for face detection
        self.face_cascade = cv2.CascadeClassifier(
            cv2.data.haarcascades + 'haarcascade_frontalface_default.xml'
        )

        # Database configuration
        self.db_config = {
            'host': 'localhost',
            'user': 'root',
            'password': '',
            'database': 'dispatch_system'
        }

        # Recognition settings
        self.reload_interval = 30
        self.similarity_threshold = 0.60  # ArcFace ~ cosine similarity threshold
        self.last_full_reload = datetime.now()
        self.current_date = datetime.now().date()

        if not os.path.exists(self.uploads_dir):
            os.makedirs(self.uploads_dir)

        self.reload_all_drivers()
        self.start_auto_reload()
        self.start_daily_reset_monitor()

    # -------------------------------
    # DB CONNECTION
    # -------------------------------
    def get_db_connection(self):
        try:
            return mysql.connector.connect(**self.db_config)
        except Exception as e:
            print(f"[DB] Connection error: {e}")
            return None

    # -------------------------------
    # DAILY RESET MONITOR
    # -------------------------------
    def start_daily_reset_monitor(self):
        """Monitor for date change and reset queue numbers at midnight"""
        def monitor_worker():
            while True:
                try:
                    current_date = datetime.now().date()
                    
                    # Check if date has changed
                    if current_date != self.current_date:
                        print(f"\n[DailyReset] Date changed from {self.current_date} to {current_date}")
                        print("[DailyReset] Queue numbers will reset for new day")
                        self.current_date = current_date
                        
                        # Optional: Archive old queue data
                        self.archive_previous_day_queue()
                    
                    # Check every minute
                    time.sleep(60)
                    
                except Exception as e:
                    print(f"[DailyReset] Monitor error: {e}")
                    time.sleep(60)

        threading.Thread(target=monitor_worker, daemon=True).start()
        print(f"[Info] Daily reset monitor started (current date: {self.current_date})")

    def archive_previous_day_queue(self):
        """Archive or clean up previous day's queue using queue_date"""
        conn = self.get_db_connection()
        if not conn:
            return
        
        try:
            cursor = conn.cursor()
            
            today = datetime.now().date()
            
            # Update 'Onqueue' entries from previous days to 'Expired' using queue_date
            cursor.execute("""
                UPDATE queue 
                SET status = 'Expired' 
                WHERE status = 'Onqueue' 
                AND queue_date < %s
            """, (today,))
            
            expired_count = cursor.rowcount
            conn.commit()
            cursor.close()
            conn.close()
            
            if expired_count > 0:
                print(f"[DailyReset] Expired {expired_count} old queue entries")
            
        except Exception as e:
            print(f"[DailyReset] Archive error: {e}")
            try:
                conn.close()
            except:
                pass
    # -------------------------------
    # AUTO RELOAD THREAD
    # -------------------------------
    def start_auto_reload(self):
        def worker():
            while True:
                time.sleep(self.reload_interval)
                try:
                    print("\n[AutoReload] Checking for updates...")
                    self.reload_all_drivers()
                except Exception as e:
                    print(f"[AutoReload] error: {e}")

        threading.Thread(target=worker, daemon=True).start()
        print(f"[Info] Auto-reload enabled ({self.reload_interval}s interval)")

    # -------------------------------
    # VALIDATE SINGLE FACE
    # -------------------------------
    def validate_single_face(self, image_data):
        """
        Validate that exactly one face is present in the image
        Returns: dict with 'valid' (bool) and 'message' (str)
        """
        try:
            # Decode base64 image
            img_bytes = base64.b64decode(image_data.split(',')[1] if ',' in image_data else image_data)
            img = Image.open(io.BytesIO(img_bytes)).convert("RGB")
            np_img = np.array(img)
            
            # Convert to grayscale for face detection
            gray = cv2.cvtColor(np_img, cv2.COLOR_RGB2GRAY)
            
            # Detect faces using Haar Cascade
            faces = self.face_cascade.detectMultiScale(
                gray,
                scaleFactor=1.1,
                minNeighbors=5,
                minSize=(60, 60)
            )
            
            face_count = len(faces)
            
            print(f"[validate_single_face] Detected {face_count} face(s)")
            
            if face_count == 0:
                return {
                    "valid": False,
                    "message": "No face detected. Please position your face clearly in the camera.",
                    "face_count": 0
                }
            elif face_count > 1:
                return {
                    "valid": False,
                    "message": f"Multiple faces detected ({face_count}). Only one person should be in the frame.",
                    "face_count": face_count
                }
            else:
                # Check if the detected face is large enough (not too far)
                (x, y, w, h) = faces[0]
                face_area = w * h
                image_area = np_img.shape[0] * np_img.shape[1]
                face_ratio = face_area / image_area
                
                if face_ratio < 0.05:  # Face is too small (less than 5% of image)
                    return {
                        "valid": False,
                        "message": "Face is too far. Please move closer to the camera.",
                        "face_count": 1
                    }
                
                return {
                    "valid": True,
                    "message": "Valid face detected",
                    "face_count": 1
                }
                
        except Exception as e:
            print(f"[validate_single_face] Error: {e}")
            return {
                "valid": False,
                "message": f"Error validating face: {str(e)}",
                "face_count": 0
            }

    # -------------------------------
    # LOAD DRIVER EMBEDDINGS
    # -------------------------------
    def reload_all_drivers(self):
        conn = self.get_db_connection()
        if not conn:
            print("[reload_all_drivers] DB connection failed, skipping load.")
            return

        try:
            cursor = conn.cursor(dictionary=True)
            cursor.execute("""
                SELECT id, firstname, lastname, profile_pic
                FROM drivers
                WHERE profile_pic IS NOT NULL AND profile_pic != ''
            """)
            all_drivers = cursor.fetchall()
            cursor.close()
            conn.close()

            print(f"[Reload] Found {len(all_drivers)} drivers to load...")

            new_known_faces = {}
            for driver in all_drivers:
                driver_id = driver['id']
                name = f"{driver['firstname']} {driver['lastname']}".strip()
                embeddings = []
                driver_dir = os.path.join(self.uploads_dir, str(driver_id))
                samples = []

                if os.path.exists(driver_dir):
                    for f in os.listdir(driver_dir):
                        if f.lower().endswith(('.jpg', '.jpeg', '.png')):
                            samples.append(os.path.join(driver_dir, f))
                if driver.get('profile_pic') and os.path.exists(driver['profile_pic']):
                    samples.append(driver['profile_pic'])

                if not samples:
                    print(f"[Reload] No samples for {name}")
                    continue

                for img_path in samples:
                    emb = self.extract_embedding(img_path)
                    if emb is not None:
                        embeddings.append(emb)

                if not embeddings:
                    print(f"[Reload] No valid embeddings for {name}")
                    continue

                avg_emb = np.mean(embeddings, axis=0)
                avg_emb /= (np.linalg.norm(avg_emb) + 1e-8)
                face_hash = hashlib.md5(avg_emb.tobytes()).hexdigest()

                new_known_faces[str(driver_id)] = {
                    "id": driver_id,
                    "name": name,
                    "embedding": avg_emb.tolist(),
                    "samples": len(embeddings),
                    "hash": face_hash
                }
                print(f"✓ Loaded {name} ({len(embeddings)} samples)")

            self.known_faces = new_known_faces
            self.save_face_data()
            print(f"[Reload] Done. Total drivers loaded: {len(self.known_faces)}")

        except Exception as e:
            print(f"[reload_all_drivers] error: {e}")

    # -------------------------------
    # EXTRACT FACE EMBEDDING (ArcFace)
    # -------------------------------
    def extract_embedding(self, path_or_array):
        try:
            emb = DeepFace.represent(
                img_path=path_or_array,
                model_name="ArcFace",
                enforce_detection=False
            )
            if isinstance(emb, list) and "embedding" in emb[0]:
                return np.array(emb[0]["embedding"], dtype=np.float32)
            return None
        except Exception as e:
            print(f"[extract_embedding] error: {e}")
            return None

    # -------------------------------
    # RECOGNIZE FACE
    # -------------------------------
    def recognize_face(self, image_data):
        try:
            img_bytes = base64.b64decode(image_data.split(",")[1] if "," in image_data else image_data)
            img = Image.open(io.BytesIO(img_bytes)).convert("RGB")
            np_img = np.array(img)

            emb = self.extract_embedding(np_img)
            if emb is None:
                return {"success": False, "message": "Failed to extract face embedding"}

            emb /= (np.linalg.norm(emb) + 1e-8)
            best_id, best_sim = None, 0.0

            for driver_id, data in self.known_faces.items():
                sim = self.cosine_similarity(emb, np.array(data["embedding"]))
                if sim > best_sim:
                    best_sim = sim
                    best_id = driver_id

            if not best_id or best_sim < self.similarity_threshold:
                return {"success": False, "message": "Face not recognized", "similarity": float(best_sim)}

            driver = self.get_driver_info_by_id(int(best_id))
            if not driver:
                return {"success": False, "message": "Driver not found in DB"}

            return {
                "success": True,
                "recognized": True,
                "similarity": float(best_sim),
                "driver": {
                    "id": driver["id"],
                    "name": f"{driver['firstname']} {driver['lastname']}",
                    "tricycle_number": driver.get("tricycle_number", ""),
                    "contact_no": driver.get("contact_no", "")
                }
            }
        except Exception as e:
            print(f"[recognize_face] error: {e}")
            return {"success": False, "message": f"Recognition error: {e}"}

    def cosine_similarity(self, a, b):
        return np.dot(a, b) / (np.linalg.norm(a) * np.linalg.norm(b) + 1e-8)

    # -------------------------------
    # SAVE FACE DATA
    # -------------------------------
    def save_face_data(self):
        try:
            with open(self.data_file, "w") as f:
                json.dump(self.known_faces, f, indent=2)
        except Exception as e:
            print(f"[save_face_data] error: {e}")

    # -------------------------------
    # DB HELPERS
    # -------------------------------
    def get_driver_info_by_id(self, driver_id):
        conn = self.get_db_connection()
        if not conn:
            return None
        try:
            cursor = conn.cursor(dictionary=True)
            cursor.execute("SELECT * FROM drivers WHERE id=%s LIMIT 1", (driver_id,))
            driver = cursor.fetchone()
            cursor.close()
            conn.close()
            return driver
        except Exception as e:
            print(f"[get_driver_info_by_id] error: {e}")
            try: conn.close()
            except: pass
            return None

    # -------------------------------
    # QUEUE FUNCTIONS WITH HISTORY RECORDING
    # -------------------------------


    def add_to_queue(self, driver_id):
        conn = self.get_db_connection()
        if not conn:
            return {"success": False, "message": "DB connection failed"}
        try:
            cursor = conn.cursor(dictionary=True)
            
            # Get driver
            cursor.execute(
                "SELECT id, firstname, lastname, tricycle_number FROM drivers WHERE id=%s LIMIT 1",
                (driver_id,)
            )
            driver = cursor.fetchone()
            if not driver:
                cursor.close()
                conn.close()
                return {"success": False, "message": "Driver not found"}
            
            driver_name = f"{driver['firstname']} {driver['lastname']}"
            tricycle_number = driver.get("tricycle_number", "")
            
            # Check if already in queue today
            cursor.execute("""
                SELECT id FROM queue 
                WHERE driver_id=%s AND status='Onqueue' AND DATE(queued_at)=CURDATE()
            """, (driver_id,))
            existing = cursor.fetchone()
            
            if existing:
                cursor.close()
                conn.close()
                return {"success": False, "message": "Already in queue"}

            now = datetime.now()

            # Insert into queue
            cursor.execute("""
                INSERT INTO queue (driver_id, driver_name, tricycle_number, queued_at, status)
                VALUES (%s, %s, %s, %s, 'Onqueue')
            """, (driver_id, driver_name, tricycle_number, now))
            queue_id = cursor.lastrowid
            
            # Insert INQUEUE event into history
            cursor.execute("""
                INSERT INTO history (driver_id, driver_name, tricycle_number, queue_time, queue_id)
                VALUES (%s, %s, %s, %s, %s)
            """, (driver_id, driver_name, tricycle_number, now, queue_id))
            
            conn.commit()
            cursor.close()
            conn.close()
            
            print(f"[INQUEUE] Driver {driver_name} added to queue & saved to history")
            return {"success": True, "message": "Added to queue & saved to history"}

        except Exception as e:
            print(f"[add_to_queue] Error: {e}")
            try: conn.close()
            except: pass
            return {"success": False, "message": str(e)}

   # -------------------------------
# FIXED DISPATCH WITH CORRECT HISTORY RECORDING
# -------------------------------
    def dispatch_driver(self, driver_id):
        conn = self.get_db_connection()
        if not conn:
            return {"success": False, "message": "DB connection failed"}

        try:
            cursor = conn.cursor(dictionary=True)
            
            # Get driver
            cursor.execute(
                "SELECT id, firstname, lastname, tricycle_number FROM drivers WHERE id=%s LIMIT 1",
                (driver_id,)
            )
            driver = cursor.fetchone()
            if not driver:
                cursor.close()
                conn.close()
                return {"success": False, "message": "Driver not found"}

            driver_name = f"{driver['firstname']} {driver['lastname']}"
            tricycle_number = driver.get("tricycle_number", "")

            # Find queue entry
            cursor.execute("""
                SELECT id FROM queue 
                WHERE driver_id=%s AND status='Onqueue' ORDER BY queued_at ASC LIMIT 1
            """, (driver_id,))
            queue_entry = cursor.fetchone()

            if not queue_entry:
                cursor.close()
                conn.close()
                return {"success": False, "message": "Not in queue"}

            queue_id = queue_entry["id"]

            now = datetime.now()

            # Update queue entry
            cursor.execute("UPDATE queue SET status='Dispatched', dispatch_at=%s WHERE id=%s", (now, queue_id))

            # Create DISPATCH history record
            cursor.execute("""
                INSERT INTO history (driver_id, driver_name, tricycle_number, dispatch_time, queue_id)
                VALUES (%s, %s, %s, %s, %s)
            """, (driver_id, driver_name, tricycle_number, now, queue_id))

            conn.commit()
            cursor.close()
            conn.close()

            print(f"[DISPATCH] Driver {driver_name} dispatched & saved to history")
            return {"success": True, "message": "Dispatched & saved to history"}

        except Exception as e:
            print(f"[dispatch_driver] Error: {e}")
            try: conn.close()
            except: pass
            return {"success": False, "message": str(e)}

# -------------------------------
# FLASK API
# -------------------------------
app = Flask(__name__)
CORS(app)
face_system = FaceRecognitionSystem()


@app.route('/validate_single_face', methods=['POST'])
def validate_single_face():
    """
    Validate that exactly one face is present in the captured image
    """
    try:
        data = request.json
        image_data = data.get('image')

        if not image_data:
            return jsonify({'valid': False, 'message': 'No image provided'}), 400

        result = face_system.validate_single_face(image_data)
        return jsonify(result)

    except Exception as e:
        print(f"[validate_single_face] Error: {str(e)}")
        return jsonify({'valid': False, 'message': f'Error: {str(e)}'}), 500


@app.route('/check_face_duplicate', methods=['POST'])
def check_face_duplicate():
    """
    Check if the uploaded face matches any existing driver in the database
    """
    try:
        data = request.json
        image_data = data.get('image')

        if not image_data:
            return jsonify({'error': 'No image provided'}), 400

        print("[check_face_duplicate] Checking for duplicate faces...")

        # Decode base64 image
        img_bytes = base64.b64decode(image_data.split(',')[1] if ',' in image_data else image_data)
        img = Image.open(io.BytesIO(img_bytes)).convert("RGB")
        np_img = np.array(img)

        # Extract embedding from new image
        new_embedding = face_system.extract_embedding(np_img)
        
        if new_embedding is None:
            print("[check_face_duplicate] No face detected in uploaded image")
            return jsonify({'duplicate': False, 'message': 'No face detected in image'})

        # Normalize embedding
        new_embedding = new_embedding / (np.linalg.norm(new_embedding) + 1e-8)

        # Compare with all registered drivers using the same similarity threshold
        DUPLICATE_THRESHOLD = 0.40  # Lower threshold for duplicate detection (more strict)
        
        for driver_id, driver_data in face_system.known_faces.items():
            stored_embedding = np.array(driver_data["embedding"])
            similarity = face_system.cosine_similarity(new_embedding, stored_embedding)
            
            print(f"[check_face_duplicate] Comparing with {driver_data['name']}: similarity = {similarity:.4f}")
            
            if similarity >= DUPLICATE_THRESHOLD:
                print(f"[check_face_duplicate] ✗ DUPLICATE FOUND: {driver_data['name']} (similarity: {similarity:.4f})")
                return jsonify({
                    'duplicate': True,
                    'matched_driver': driver_data['name'],
                    'similarity': float(similarity),
                    'driver_id': driver_data['id']
                })

        print("[check_face_duplicate] ✓ No duplicate found - new driver can be registered")
        return jsonify({'duplicate': False})

    except Exception as e:
        print(f"[check_face_duplicate] Error: {str(e)}")
        return jsonify({'error': f'Error processing image: {str(e)}'}), 500


@app.route('/check_face_match', methods=['POST'])
def check_face_match():
    """
    Check if a new face matches an existing driver's face (for edit/update)
    """
    data = request.get_json()
    existing_path = data.get("existing_image_path")
    new_image_data = data.get("new_image")

    if not existing_path or not new_image_data:
        return jsonify({"error": "Missing data"}), 400

    try:
        # Decode new image
        image_bytes = base64.b64decode(new_image_data.split(",")[1] if "," in new_image_data else new_image_data)
        new_image = Image.open(io.BytesIO(image_bytes)).convert("RGB")
        np_new = np.array(new_image)

        # Detect face in new image
        gray = cv2.cvtColor(np_new, cv2.COLOR_RGB2GRAY)
        faces = face_system.face_cascade.detectMultiScale(gray, scaleFactor=1.1, minNeighbors=5)
        if len(faces) == 0:
            return jsonify({"same_face": False, "error": "No face detected in new image"}), 400

        # Extract embeddings
        new_emb = face_system.extract_embedding(np_new)
        if new_emb is None:
            return jsonify({"same_face": False, "error": "Failed to extract embedding from new image"}), 400
        new_emb /= (np.linalg.norm(new_emb) + 1e-8)

        # Load existing image
        if not os.path.exists(existing_path):
            return jsonify({"same_face": False, "error": "Existing image not found"}), 400
        existing_emb = face_system.extract_embedding(existing_path)
        if existing_emb is None:
            return jsonify({"same_face": False, "error": "Failed to extract embedding from existing image"}), 400
        existing_emb /= (np.linalg.norm(existing_emb) + 1e-8)

        # Compute similarity
        similarity = float(face_system.cosine_similarity(new_emb, existing_emb))
        SAME_FACE_THRESHOLD = 0.60  # Adjust this as needed (ArcFace cosine similarity)

        is_same = similarity >= SAME_FACE_THRESHOLD

        return jsonify({
            "same_face": is_same,
            "similarity": similarity
        })

    except Exception as e:
        print(f"[check_face_match] Error: {str(e)}")
        return jsonify({"error": str(e)}), 500


@app.route("/recognize", methods=["POST"])
def recognize():
    data = request.get_json()
    if not data or "image" not in data:
        return jsonify({"success": False, "message": "Image required"})
    return jsonify(face_system.recognize_face(data["image"]))


@app.route('/inqueue', methods=['POST'])
def inqueue():
    """Add driver to queue - Reports-friendly version"""
    try:
        data = request.get_json()
        driver_id = data.get('driver_id')
        
        if not driver_id:
            return jsonify({
                'success': False,
                'message': 'Driver ID required'
            }), 400
        
        conn = face_system.get_db_connection()
        if not conn:
            return jsonify({
                'success': False,
                'message': 'Database connection failed'
            }), 500
        
        try:
            cursor = conn.cursor(dictionary=True)
            
            # Get driver info
            cursor.execute(
                "SELECT id, firstname, lastname, tricycle_number FROM drivers WHERE id=%s LIMIT 1",
                (driver_id,)
            )
            driver = cursor.fetchone()
            
            if not driver:
                cursor.close()
                conn.close()
                return jsonify({
                    'success': False,
                    'message': 'Driver not found'
                }), 404
            
            driver_name = f"{driver['firstname']} {driver['lastname']}"
            tricycle_number = driver.get("tricycle_number", "")
            
            now = datetime.now()
            today = now.date()
            
            # Check if already in queue today
            # NOTE: Still using DATE(queued_at) for compatibility with existing reports
            cursor.execute("""
                SELECT id, queue_number FROM queue 
                WHERE driver_id = %s 
                AND status = 'Onqueue' 
                AND DATE(queued_at) = CURDATE()
            """, (driver_id,))
            existing = cursor.fetchone()
            
            if existing:
                cursor.close()
                conn.close()
                return jsonify({
                    'success': False,
                    'message': f'Already in queue as #{existing["queue_number"]}',
                    'queue_number': existing['queue_number']
                }), 400
            
            # Get next queue number
            # NOTE: Using queue_date here for accuracy, but reports can still use DATE(queued_at)
            cursor.execute("""
                SELECT COALESCE(MAX(queue_number), 0) as max_num 
                FROM queue 
                WHERE queue_date = %s
            """, (today,))
            max_row = cursor.fetchone()
            next_queue_number = max_row['max_num'] + 1
            
            # Insert into queue
            # IMPORTANT: Set both queued_at AND queue_date so reports work with either
            cursor.execute("""
                INSERT INTO queue 
                (driver_id, driver_name, tricycle_number, queue_number, queue_date, queued_at, status)
                VALUES (%s, %s, %s, %s, %s, %s, 'Onqueue')
            """, (driver_id, driver_name, tricycle_number, next_queue_number, today, now))
            
            queue_id = cursor.lastrowid
            
            # Insert into history (NO CHANGES - reports still work)
            cursor.execute("""
                INSERT INTO history (driver_id, driver_name, tricycle_number, queue_time, queue_id)
                VALUES (%s, %s, %s, %s, %s)
            """, (driver_id, driver_name, tricycle_number, now, queue_id))
            
            conn.commit()
            cursor.close()
            conn.close()
            
            print(f"[INQUEUE] Driver {driver_name} added as Queue #{next_queue_number}")
            
            return jsonify({
                'success': True,
                'message': f'Added to queue as #{next_queue_number}',
                'queue_number': next_queue_number
            }), 200
            
        except mysql.connector.IntegrityError as ie:
            print(f"[inqueue] Duplicate prevented: {ie}")
            try:
                conn.rollback()
                conn.close()
            except:
                pass
            return jsonify({
                'success': False,
                'message': 'Queue number conflict. Please try again.'
            }), 409
            
        except Exception as e:
            print(f"[inqueue] Database error: {e}")
            try:
                conn.rollback()
                conn.close()
            except:
                pass
            return jsonify({
                'success': False,
                'message': f'Database error: {str(e)}'
            }), 500
            
    except Exception as e:
        print(f"[inqueue] Error: {e}")
        return jsonify({
            'success': False,
            'message': 'Server error occurred'
        }), 500

@app.route('/dispatch', methods=['POST'])
def dispatch():
    """Dispatch driver from queue - Reports-friendly version"""
    try:
        data = request.get_json()
        driver_id = data.get('driver_id')
        
        if not driver_id:
            return jsonify({
                'success': False,
                'message': 'Driver ID required'
            }), 400
        
        conn = face_system.get_db_connection()
        if not conn:
            return jsonify({
                'success': False,
                'message': 'Database connection failed'
            }), 500
        
        try:
            cursor = conn.cursor(dictionary=True)
            
            # Get driver info
            cursor.execute(
                "SELECT id, firstname, lastname, tricycle_number FROM drivers WHERE id=%s LIMIT 1",
                (driver_id,)
            )
            driver = cursor.fetchone()
            
            if not driver:
                cursor.close()
                conn.close()
                return jsonify({
                    'success': False,
                    'message': 'Driver not found'
                }), 404
            
            driver_name = f"{driver['firstname']} {driver['lastname']}"
            tricycle_number = driver.get("tricycle_number", "")
            
            # Find queue entry for TODAY
            # NOTE: Still using DATE(queued_at) for compatibility with existing reports
            cursor.execute("""
                SELECT id, queue_number FROM queue 
                WHERE driver_id = %s 
                AND status = 'Onqueue' 
                AND DATE(queued_at) = CURDATE()
                ORDER BY queued_at ASC 
                LIMIT 1
            """, (driver_id,))
            queue_entry = cursor.fetchone()
            
            if not queue_entry:
                cursor.close()
                conn.close()
                return jsonify({
                    'success': False,
                    'message': 'Driver not in queue today'
                }), 400
            
            queue_id = queue_entry["id"]
            queue_number = queue_entry["queue_number"]
            now = datetime.now()
            
            # Update queue status (NO CHANGES - reports still work)
            cursor.execute("""
                UPDATE queue 
                SET status = 'Dispatched', dispatch_at = %s 
                WHERE id = %s
            """, (now, queue_id))
            
            # Insert dispatch record into history (NO CHANGES - reports still work)
            cursor.execute("""
                INSERT INTO history (driver_id, driver_name, tricycle_number, dispatch_time, queue_id)
                VALUES (%s, %s, %s, %s, %s)
            """, (driver_id, driver_name, tricycle_number, now, queue_id))
            
            conn.commit()
            cursor.close()
            conn.close()
            
            print(f"[DISPATCH] Queue #{queue_number} ({driver_name}) dispatched")
            
            return jsonify({
                'success': True,
                'message': f'Queue #{queue_number} dispatched successfully'
            }), 200
            
        except Exception as e:
            print(f"[dispatch] Database error: {e}")
            try:
                conn.rollback()
                conn.close()
            except:
                pass
            return jsonify({
                'success': False,
                'message': f'Database error: {str(e)}'
            }), 500
            
    except Exception as e:
        print(f"[dispatch] Error: {e}")
        return jsonify({
            'success': False,
            'message': 'Server error occurred'
        }), 500

@app.route("/reload", methods=["POST"])
def reload_drivers():
    face_system.reload_all_drivers()
    return jsonify({"success": True, "count": len(face_system.known_faces)})


@app.route("/health", methods=["GET"])
def health():
    return jsonify({"status": "ok", "faces_loaded": len(face_system.known_faces), "current_date": str(face_system.current_date)})

@app.route('/remove_now_serving', methods=['POST'])
def remove_now_serving():
    """Remove the currently serving driver (first in queue) - Requires authenticated driver"""
    try:
        data = request.json
        remover_driver_id = data.get('remover_driver_id')
        remover_driver_name = data.get('remover_driver_name')
        
        if not remover_driver_id or not remover_driver_name:
            return jsonify({
                'success': False,
                'message': 'Authentication required'
            }), 400
        
        conn = face_system.get_db_connection()
        if not conn:
            return jsonify({
                'success': False,
                'message': 'Database connection failed'
            }), 500
        
        try:
            cursor = conn.cursor(dictionary=True)
            
            # Get the first driver in queue (now serving)
            cursor.execute("""
                SELECT id, driver_id, driver_name, tricycle_number, queue_number
                FROM queue 
                WHERE status = 'Onqueue' 
                AND DATE(queued_at) = CURDATE()
                ORDER BY queued_at ASC 
                LIMIT 1
            """)
            now_serving = cursor.fetchone()
            
            if not now_serving:
                cursor.close()
                conn.close()
                return jsonify({
                    'success': False,
                    'message': 'No driver currently serving'
                }), 400
            
            queue_id = now_serving['id']
            removed_driver_id = now_serving['driver_id']
            removed_driver_name = now_serving['driver_name']
            removed_tricycle = now_serving['tricycle_number']
            removed_queue_number = now_serving['queue_number']
            
            now = datetime.now()
            
            # Update queue status to Removed
            cursor.execute("""
                UPDATE queue 
                SET status = 'Removed', dispatch_at = %s 
                WHERE id = %s
            """, (now, queue_id))
            
            # Log the removal in removal_logs table
            cursor.execute("""
                INSERT INTO removal_logs 
                (driver_id, driver_name, tricycle_number, queue_number, 
                 remover_driver_id, remover_driver_name, removed_at, reason)
                VALUES (%s, %s, %s, %s, %s, %s, %s, %s)
            """, (
                removed_driver_id, 
                removed_driver_name, 
                removed_tricycle, 
                removed_queue_number,
                remover_driver_id, 
                remover_driver_name, 
                now,
                'Forgot to dispatch - Removed from Now Serving'
            ))
            
            conn.commit()
            cursor.close()
            conn.close()
            
            print(f"[REMOVE_NOW_SERVING] Queue #{removed_queue_number} ({removed_driver_name}) removed by {remover_driver_name}")
            
            return jsonify({
                'success': True,
                'message': 'Driver removed from Now Serving successfully',
                'removed_driver_name': removed_driver_name,
                'removed_queue_number': removed_queue_number
            }), 200
            
        except Exception as e:
            print(f"[remove_now_serving] Database error: {e}")
            try:
                conn.rollback()
                conn.close()
            except:
                pass
            return jsonify({
                'success': False,
                'message': f'Database error: {str(e)}'
            }), 500
            
    except Exception as e:
        print(f"[remove_now_serving] Error: {e}")
        return jsonify({
            'success': False,
            'message': 'Server error occurred'
        }), 500

@app.route('/get_removal_logs', methods=['GET'])
def get_removal_logs():
    """Get all removal logs, ordered by most recent"""
    conn = face_system.get_db_connection()
    if not conn:
        return jsonify({'success': False, 'message': 'Database connection failed'}), 500
    
    try:
        cursor = conn.cursor(dictionary=True)
        cursor.execute("""
            SELECT 
                driver_id,
                driver_name, 
                tricycle_number, 
                queue_number,
                remover_driver_id,
                remover_driver_name,
                removed_at,
                reason
            FROM removal_logs 
            ORDER BY removed_at DESC 
            LIMIT 100
        """)
        logs = cursor.fetchall()
        cursor.close()
        conn.close()
        
        return jsonify({'success': True, 'logs': logs})
        
    except Exception as e:
        print(f"[get_removal_logs] Error: {e}")
        try: conn.close()
        except: pass
        return jsonify({'success': False, 'message': str(e)}), 500

if __name__ == "__main__":
    print(f"[Info] Loaded {len(face_system.known_faces)} driver embeddings")
    print(f"[Info] Current date: {face_system.current_date}")
    print("[Info] Server running at: http://127.0.0.1:5000")
    app.run(host="127.0.0.1", port=5000, debug=True, use_reloader=False)