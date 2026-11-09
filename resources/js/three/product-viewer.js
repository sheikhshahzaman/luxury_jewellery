// Product page "View in 3D": an illustrative model per category that
// follows the metal picker. Orbit + zoom with damping.
import { OrbitControls } from 'three/addons/controls/OrbitControls.js';
import { createStage, reducedMotion } from './stage.js';
import { createGlints, createModel, tweenMetal } from './models.js';

export function mountProductViewer(host) {
    const stage = createStage(host, { fov: 30, cameraZ: 9, exposure: 1.15 });
    const { scene, camera, renderer } = stage;

    const model = createModel(host.dataset.model, host.dataset.tone || 'gold');
    if (host.dataset.model === 'ring') {
        model.rotation.set(0.35, 0, 0.1);
        model.position.y = -0.35; // leave head-room for the signet face under the arch
    }
    scene.add(model);
    const glints = createGlints(40, 2.4);
    scene.add(glints);

    const controls = new OrbitControls(camera, renderer.domElement);
    controls.enableDamping = true;
    controls.enablePan = false;
    controls.minDistance = 3.6;
    controls.maxDistance = 12;
    controls.autoRotate = !reducedMotion;
    controls.autoRotateSpeed = 1.2;
    renderer.domElement.addEventListener('pointerdown', () => (controls.autoRotate = false), { once: true });

    stage.onFrame((t) => {
        controls.update();
        glints.userData.tick(t);
    });
    stage.go();

    document.addEventListener('eluxee:metal', (e) => {
        tweenMetal(model.userData.metal, e.detail.tone);
    });

    return stage;
}
